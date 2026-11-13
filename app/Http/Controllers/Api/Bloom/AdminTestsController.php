<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomCase;
use App\Models\BloomCaseTest;
use App\Models\ScreeningSample;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /bloom-admin/tests — every screening test and result recorded through the Bloom app.
 * Read from the NCSR screening_samples rows (the source of truth, so results entered later at the lab show
 * too), limited to samples linked to a Bloom case. Admins see their facilities; a health worker sees their own.
 */
class AdminTestsController extends Controller
{
    use CaseConcerns;

    public function index(Request $request): JsonResponse
    {
        $me = $this->staff();
        $isReviewer = $this->canReview($me);
        if (!$isReviewer && !$this->isActiveNavigator($me)) {
            return $this->deny();
        }

        $v = $request->validate([
            'status'     => ['nullable', 'in:collected,result_released'],
            'finding'    => ['nullable', 'in:normal,suspicious,neutral'],
            'testType'   => ['nullable', 'string', 'max:50'],
            'facilityId' => ['nullable', 'integer'],
            'search'     => ['nullable', 'string', 'max:100'],
            'dateFrom'   => ['nullable', 'date_format:Y-m-d'],
            'dateTo'     => ['nullable', 'date_format:Y-m-d'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'perPage'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Bloom-linked samples this user may see.
        $links = BloomCaseTest::query();
        if ($isReviewer) {
            $visible = $me->visibleFacilityIds();
            if ($visible !== null) {
                $links->whereIn('case_id', BloomCase::whereIn('facility_id', $visible ?: [0])->select('id'));
            }
        } else {
            $links->whereIn('case_id', BloomCase::where('navigator_id', $me->id)->select('id'));
        }

        $base = ScreeningSample::query()
            ->whereIn('sampleId', (clone $links)->select('sample_id'))
            ->when($v['testType'] ?? null, fn ($q, $t) => $q->where('testType', $t))
            ->when($v['facilityId'] ?? null, fn ($q, $f) => $q->whereIn('sampleId', BloomCaseTest::whereIn('case_id', BloomCase::where('facility_id', $f)->select('id'))->select('sample_id')))
            ->when($v['dateFrom'] ?? null, fn ($q, $d) => $q->whereDate('sampleDate', '>=', $d))
            ->when($v['dateTo'] ?? null, fn ($q, $d) => $q->whereDate('sampleDate', '<=', $d));

        if (!empty($v['search'])) {
            $term = trim($v['search']);
            $caseId = preg_match('/^blm-?0*(\d+)$/i', $term, $m) ? (int) $m[1] : null;
            $base->where(function ($q) use ($term, $caseId) {
                $q->where('clientId', 'like', "%{$term}%")
                  ->orWhere('sampleRef', 'like', "%{$term}%")
                  ->orWhereHas('client', fn ($c) => $c->where('fullName', 'like', "%{$term}%")->orWhere('phoneNumber', 'like', "%{$term}%"));
                if ($caseId) {
                    $q->orWhereIn('sampleId', BloomCaseTest::where('case_id', $caseId)->select('sample_id'));
                }
            });
        }

        // Tiles ignore the status / finding filters so they stay usable as filters.
        $summary = [
            'total'      => (clone $base)->count(),
            'collected'  => (clone $base)->where('status', 'collected')->count(),
            'released'   => (clone $base)->where('status', 'result_released')->count(),
            'normal'     => (clone $base)->where('status', 'result_released')->where('resultSeverity', 'normal')->count(),
            'suspicious' => (clone $base)->where('status', 'result_released')->whereIn('resultSeverity', ['urgent', 'abnormal'])->count(),
            'neutral'    => (clone $base)->where('status', 'result_released')->where(fn ($q) => $q->where('resultSeverity', 'neutral')->orWhereNull('resultSeverity'))->count(),
        ];

        $list = clone $base;
        if (!empty($v['status'])) {
            $list->where('status', $v['status']);
        }
        if (($v['finding'] ?? null) === 'suspicious') {
            $list->where('status', 'result_released')->whereIn('resultSeverity', ['urgent', 'abnormal']);
        } elseif (($v['finding'] ?? null) === 'normal') {
            $list->where('status', 'result_released')->where('resultSeverity', 'normal');
        } elseif (($v['finding'] ?? null) === 'neutral') {
            $list->where('status', 'result_released')->where(fn ($q) => $q->where('resultSeverity', 'neutral')->orWhereNull('resultSeverity'));
        }

        $perPage = (int) ($v['perPage'] ?? 20);
        $page = (int) ($v['page'] ?? 1);
        $total = (clone $list)->count();
        $rows = $list->with(['client', 'facility'])
            ->orderByRaw("CASE WHEN status = 'collected' THEN 0 ELSE 1 END")
            ->orderByDesc('sampleDate')->orderByDesc('sampleId')
            ->skip(($page - 1) * $perPage)->take($perPage)->get();

        $linkRows = BloomCaseTest::whereIn('sample_id', $rows->pluck('sampleId'))->get()->keyBy('sample_id');
        $cases = BloomCase::with('navigator')->whereIn('id', $linkRows->pluck('case_id'))->get()->keyBy('id');

        $tests = collect(ScreeningTestCatalog::keys())->map(fn ($k) => ScreeningTestCatalog::find($k))
            ->filter(fn ($t) => $t && ($t['cancerType'] ?? null) === 'breast')->values();

        return response()->json([
            'status'  => true,
            'summary' => $summary,
            'total'   => $total,
            'tests'   => $tests,
            'results' => $rows->map(function ($s) use ($linkRows, $cases) {
                $case = $cases[$linkRows[$s->sampleId]->case_id ?? 0] ?? null;
                $c = $s->client;

                return [
                    'sampleId'       => $s->sampleId,
                    'sampleRef'      => $s->sampleRef,
                    'testType'       => $s->testType,
                    'status'         => $s->status,
                    'sampleDate'     => $s->sampleDate ? substr((string) $s->sampleDate, 0, 10) : null,
                    'specimenType'   => $s->specimenType,
                    'collectedBy'    => $s->collectedBy,
                    'result'         => $s->result,
                    'resultSeverity' => $s->resultSeverity,
                    'resultValue'    => $s->resultValue,
                    'resultDetail'   => $s->resultDetail,
                    'resultMeta'     => is_array($s->resultMeta) ? $s->resultMeta : (json_decode((string) $s->resultMeta, true) ?: null),
                    'resultDate'     => $s->resultDate ? substr((string) $s->resultDate, 0, 10) : null,
                    'resultNotes'    => $s->resultNotes,
                    'client'         => $c ? ['clientId' => $c->clientId, 'fullName' => $c->fullName, 'age' => $c->age, 'phoneNumber' => $c->phoneNumber] : ['clientId' => $s->clientId],
                    'facility'       => $s->facility ? ['id' => (int) $s->facility->facilityId, 'name' => $s->facility->facilityName] : null,
                    'case'           => $case ? [
                        'id'        => $case->id,
                        'code'      => $case->code(),
                        'navigator' => $case->navigator ? trim("{$case->navigator->firstName} {$case->navigator->lastName}") : null,
                    ] : null,
                ];
            })->values(),
        ]);
    }
}
