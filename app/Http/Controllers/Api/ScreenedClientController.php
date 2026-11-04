<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreenedClientController extends Controller
{
    /** cancer type => relation on ScreeningVisit. Types whose relation is missing are skipped. */
    private const RELATIONS = [
        'cervical'   => 'cervicalScreening',
        'breast'     => 'breastScreening',
        'prostate'   => 'prostateScreening',
        'colorectal' => 'colorectalScreening',
        'liver'      => 'liverScreening',
    ];

    private function relations(): array
    {
        $visit = new ScreeningVisit();
        return array_filter(self::RELATIONS, fn ($rel) => method_exists($visit, $rel));
    }

    /** Clients with at least one saved screening, limited to the user's facilities. */
    private function screenedQuery(?array $visible, array $rels, array $f = [])
    {
        $cancer = $f['cancerType'] ?? null;
        $use = $cancer ? array_intersect_key($rels, [$cancer => 1]) : $rels;

        $q = Client::query()->when($visible !== null, fn ($c) => $c->whereIn('facilityId', $visible));

        if (!$use) {
            return $q->whereRaw('1 = 0');
        }

        return $q->whereHas('visits', function ($v) use ($use, $f) {
            $v->where(function ($w) use ($use, $f) {
                foreach ($use as $rel) {
                    $w->orWhereHas($rel, function ($m) use ($f) {
                        if (!empty($f['result']))   $m->where('screeningResult', $f['result']);
                        if (!empty($f['dateFrom'])) $m->whereDate('screeningDate', '>=', $f['dateFrom']);
                        if (!empty($f['dateTo']))   $m->whereDate('screeningDate', '<=', $f['dateTo']);
                    });
                }
            });
        });
    }

    /**
     * GET /screened-clients
     * ?search= &cancerType= &result=suspicious|non_suspicious &lab=pending|abnormal|urgent
     *  &dateFrom= &dateTo= &page= &perPage=
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();
        $rels = $this->relations();
        $search = trim((string) $request->query('search', ''));

        $filters = $request->only(['cancerType', 'result', 'dateFrom', 'dateTo']);
        $query = $this->screenedQuery($visible, $rels, $filters);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('clientId', 'like', "%{$search}%")
                  ->orWhere('fullName', 'like', "%{$search}%")
                  ->orWhere('phoneNumber', 'like', "%{$search}%")
                  ->orWhere('hospitalNumber', 'like', "%{$search}%");
            });
        }

        if ($lab = $request->query('lab')) {
            $labQuery = ScreeningSample::query()->select('clientId');
            if ($lab === 'pending') {
                $labQuery->where('status', 'collected');
            } elseif ($lab === 'urgent') {
                $labQuery->where('status', 'result_released')->where('resultSeverity', 'urgent');
            } elseif ($lab === 'abnormal') {
                $labQuery->where('status', 'result_released')->whereIn('resultSeverity', ['abnormal', 'urgent']);
            }
            $query->whereIn('clientId', $labQuery);
        }

        $page = $query
            ->withMax('visits', 'visitId')
            ->with(['visits' => fn ($v) => $v->with(array_values($rels))])
            ->orderByDesc('visits_max_visitId')
            ->paginate(min((int) $request->query('perPage', 20), 100));

        $ids = collect($page->items())->pluck('clientId');
        $samples = ScreeningSample::whereIn('clientId', $ids)->get()->groupBy('clientId');

        $rows = collect($page->items())->map(function (Client $c) use ($rels, $samples) {
            $latest = [];
            foreach ($rels as $type => $rel) {
                $best = null;
                foreach ($c->visits as $visit) {
                    $rec = $visit->{$rel};
                    if (!$rec) continue;
                    $d = $rec->screeningDate ? (string) $rec->screeningDate : '';
                    if (!$best || $d >= $best['d']) {
                        $best = ['d' => $d, 'rec' => $rec];
                    }
                }
                if ($best) {
                    $latest[] = [
                        'cancerType'      => $type,
                        'screeningDate'   => $best['d'] ? substr($best['d'], 0, 10) : null,
                        'screeningResult' => $best['rec']->screeningResult,
                    ];
                }
            }

            $mine = $samples->get($c->clientId, collect());
            $dates = collect($latest)->pluck('screeningDate')->filter()->sort()->values();

            return [
                'clientId'       => $c->clientId,
                'fullName'       => $c->fullName,
                'gender'         => $c->gender,
                'age'            => $c->age,
                'phoneNumber'    => $c->phoneNumber,
                'hospitalNumber' => $c->hospitalNumber,
                'visitCount'     => $c->visits->count(),
                'lastScreeningDate' => $dates->last(),
                'screenings'     => $latest,
                'labs' => [
                    'pending'  => $mine->where('status', 'collected')->count(),
                    'released' => $mine->where('status', 'result_released')->count(),
                    'abnormal' => $mine->where('status', 'result_released')->where('resultSeverity', 'abnormal')->count(),
                    'urgent'   => $mine->where('status', 'result_released')->where('resultSeverity', 'urgent')->count(),
                ],
            ];
        })->values();

        $all = $this->screenedQuery($visible, $rels);
        $scope = fn ($q) => $q->when($visible !== null, fn ($x) => $x->whereIn('facilityId', $visible));

        return response()->json([
            'clients' => $rows,
            'total'   => $page->total(),
            'page'    => $page->currentPage(),
            'perPage' => $page->perPage(),
            'summary' => [
                'screened'   => (clone $all)->count(),
                'suspicious' => $this->screenedQuery($visible, $rels, ['result' => 'suspicious'])->count(),
                'awaiting'   => $scope(ScreeningSample::query())->where('status', 'collected')->distinct()->count('clientId'),
                'urgentLabs' => $scope(ScreeningSample::query())->where('status', 'result_released')->where('resultSeverity', 'urgent')->distinct()->count('clientId'),
            ],
        ]);
    }
}
