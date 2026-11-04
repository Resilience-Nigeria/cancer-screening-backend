<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Facility;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use App\Models\User;
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


    private const SUSPICIOUS_OUTCOMES = ['suspicious', 'urgent_referral'];

    /** Finding for one screening record: its own result, else the HPV result, else the visit's overall outcome. */
    private function findingFor($rec, $visit): ?string
    {
        if (!empty($rec->screeningResult)) return $rec->screeningResult;

        $hpv = strtolower((string) ($rec->hpvResult ?? ''));
        if ($hpv !== '') {
            if (str_contains($hpv, 'pos')) return 'suspicious';
            if (str_contains($hpv, 'neg')) return 'non_suspicious';
        }
        $o = (string) ($visit->overallOutcome ?? '');
        if ($o === '') return null;
        return in_array($o, self::SUSPICIOUS_OUTCOMES, true) ? 'suspicious' : 'non_suspicious';
    }

    /**
     * Visit matches the cancer-type / finding / date filters. Where a screening row has no result or date of its own,
     * the visit's overall outcome and visit date are used, the same way the list displays them.
     */
    private function matchVisit($v, array $use, array $f): void
    {
        $v->where(function ($w) use ($use, $f) {
            foreach ($use as $rel) {
                $w->orWhere(function ($x) use ($rel, $f) {
                    $x->whereHas($rel);

                    if (!empty($f['result'])) {
                        $x->where(function ($r) use ($rel, $f) {
                            $r->whereHas($rel, fn ($m) => $m->where('screeningResult', $f['result']))
                              ->orWhere(function ($y) use ($rel, $f) {
                                  $y->whereHas($rel, fn ($m) => $m->whereNull('screeningResult'));
                                  if ($f['result'] === 'suspicious') {
                                      $y->whereIn('overallOutcome', self::SUSPICIOUS_OUTCOMES);
                                  } else {
                                      $y->whereNotNull('overallOutcome')->whereNotIn('overallOutcome', self::SUSPICIOUS_OUTCOMES);
                                  }
                              });
                        });
                    }

                    if (!empty($f['dateFrom']) || !empty($f['dateTo'])) {
                        $x->where(function ($d) use ($rel, $f) {
                            $d->whereHas($rel, function ($m) use ($f) {
                                  if (!empty($f['dateFrom'])) $m->whereDate('screeningDate', '>=', $f['dateFrom']);
                                  if (!empty($f['dateTo']))   $m->whereDate('screeningDate', '<=', $f['dateTo']);
                              })
                              ->orWhere(function ($y) use ($rel, $f) {
                                  $y->whereHas($rel, fn ($m) => $m->whereNull('screeningDate'));
                                  if (!empty($f['dateFrom'])) $y->whereDate('visitDate', '>=', $f['dateFrom']);
                                  if (!empty($f['dateTo']))   $y->whereDate('visitDate', '<=', $f['dateTo']);
                              });
                        });
                    }
                });
            }
        });
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

        $hasScreeningFilter = !empty($f['result']) || !empty($f['dateFrom']) || !empty($f['dateTo']) || !empty($f['facilityId']);

        return $q->where(function ($outer) use ($use, $f, $visible, $hasScreeningFilter) {
            $outer->whereHas('visits', function ($v) use ($use, $f) {
                if (!empty($f['facilityId'])) {
                    $v->where('facilityId', $f['facilityId']);   // screened at this facility
                }
                $this->matchVisit($v, $use, $f);
            });

            // A client with a lab test counts as screened even if the findings row is not saved yet.
            if (!$hasScreeningFilter) {
                $outer->orWhereIn('clientId', ScreeningSample::query()->select('clientId')
                    ->when($visible !== null, fn ($x) => $x->whereIn('facilityId', $visible))
                    ->when(!empty($f['cancerType']), fn ($x) => $x->where('cancerType', $f['cancerType'])));
            }
        });
    }

    /** Visits (one row per visit) that match the same filters, for the per-facility analytics. */
    private function visitQuery(?array $visible, array $rels, array $f, string $search, ?string $lab)
    {
        $cancer = $f['cancerType'] ?? null;
        $use = $cancer ? array_intersect_key($rels, [$cancer => 1]) : $rels;

        $q = ScreeningVisit::query()->when($visible !== null, fn ($v) => $v->whereIn('facilityId', $visible));
        if (!$use) {
            return $q->whereRaw('1 = 0');
        }

        $this->matchVisit($q, $use, $f);

        if (!empty($f['facilityId'])) {
            $q->where('facilityId', $f['facilityId']);
        }
        if ($search !== '') {
            $q->whereHas('client', fn ($c) => $c->where('clientId', 'like', "%{$search}%")
                ->orWhere('fullName', 'like', "%{$search}%")
                ->orWhere('phoneNumber', 'like', "%{$search}%")
                ->orWhere('hospitalNumber', 'like', "%{$search}%"));
        }
        if ($lab) {
            $q->whereIn('clientId', $this->labClients($lab));
        }
        return $q;
    }

    private function labClients(string $lab)
    {
        $labQuery = ScreeningSample::query()->select('clientId');
        if ($lab === 'pending') {
            $labQuery->where('status', 'collected');
        } elseif ($lab === 'urgent') {
            $labQuery->where('status', 'result_released')->where('resultSeverity', 'urgent');
        } elseif ($lab === 'abnormal') {
            $labQuery->where('status', 'result_released')->whereIn('resultSeverity', ['abnormal', 'urgent']);
        }
        return $labQuery;
    }


    /** Navigators only ever see their own facility; everyone else keeps their normal scope. */
    private function scopeFor($user): ?array
    {
        $visible = $user->visibleFacilityIds();

        $role = strtolower((string) (
            is_string($user->role ?? null) ? $user->role
            : ($user->role->name ?? $user->roleName ?? $user->userRole ?? '')
        ));
        $isNavigator = str_contains($role, 'navigator')
            || (method_exists($user, 'hasRole') && rescue(fn () => $user->hasRole('navigator'), false, false));

        if ($isNavigator && !empty($user->facilityId)) {
            return [$user->facilityId];
        }
        return $visible;
    }

    private function userName($u): string
    {
        $name = $u->fullName ?? $u->name ?? null;
        if (!$name) {
            $name = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
        }
        return $name ?: ($u->email ?? 'Unknown user');
    }

    /** id => display name for the users who created these visits. */
    private function userNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) return [];
        $model = new User();
        return User::whereIn($model->getKeyName(), $ids)->get()
            ->mapWithKeys(fn ($u) => [$u->getKey() => $this->userName($u)])->all();
    }

    /** Clients, visits and suspicious clients per person who did the screening. */
    private function screenerAnalytics(?array $visible, array $rels, array $f, string $search, ?string $lab): array
    {
        $f = array_diff_key($f, ['facilityId' => 1]);
        $totals = $this->visitQuery($visible, $rels, $f, $search, $lab)
            ->selectRaw('createdBy, COUNT(DISTINCT clientId) as clients, COUNT(*) as visits')
            ->groupBy('createdBy')->get();

        $suspicious = $this->visitQuery($visible, $rels, array_merge($f, ['result' => 'suspicious']), $search, $lab)
            ->selectRaw('createdBy, COUNT(DISTINCT clientId) as n')
            ->groupBy('createdBy')->pluck('n', 'createdBy');

        $names = $this->userNames($totals->pluck('createdBy'));

        return $totals->map(function ($r) use ($names, $suspicious) {
            $clients = (int) $r->clients;
            $susp = (int) ($suspicious[$r->createdBy] ?? 0);
            return [
                'userId'         => $r->createdBy,
                'name'           => $r->createdBy ? ($names[$r->createdBy] ?? 'Unknown user') : 'Not recorded',
                'clients'        => $clients,
                'visits'         => (int) $r->visits,
                'suspicious'     => $susp,
                'suspiciousRate' => $clients ? round($susp / $clients * 100, 1) : 0,
            ];
        })->sortByDesc('clients')->values()->all();
    }

    /** Clients screened, visits and suspicious clients per screening facility. */
    private function facilityAnalytics(?array $visible, array $rels, array $f, string $search, ?string $lab): array
    {
        $totals = $this->visitQuery($visible, $rels, $f, $search, $lab)
            ->selectRaw('facilityId, COUNT(DISTINCT clientId) as clients, COUNT(*) as visits')
            ->groupBy('facilityId')
            ->get()
            ->keyBy('facilityId');

        $suspicious = $this->visitQuery($visible, $rels, array_merge($f, ['result' => 'suspicious']), $search, $lab)
            ->selectRaw('facilityId, COUNT(DISTINCT clientId) as n')
            ->groupBy('facilityId')
            ->pluck('n', 'facilityId');

        $names = Facility::whereIn('facilityId', $totals->keys())->get(['facilityId', 'facilityName', 'facilityState'])->keyBy('facilityId');

        return $totals->map(function ($row, $fid) use ($names, $suspicious) {
            $clients = (int) $row->clients;
            $susp = (int) ($suspicious[$fid] ?? 0);
            return [
                'facilityId'     => $fid,
                'facilityName'   => $names[$fid]->facilityName ?? 'Unknown facility',
                'facilityState'  => $names[$fid]->facilityState ?? null,
                'clients'        => $clients,
                'visits'         => (int) $row->visits,
                'suspicious'     => $susp,
                'suspiciousRate' => $clients ? round($susp / $clients * 100, 1) : 0,
            ];
        })->sortByDesc('clients')->values()->all();
    }

    /**
     * GET /screened-clients
     * ?search= &cancerType= &result=suspicious|non_suspicious &lab=pending|abnormal|urgent
     *  &dateFrom= &dateTo= &page= &perPage=
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $this->scopeFor($user);
        $rels = $this->relations();
        $search = trim((string) $request->query('search', ''));

        $filters = $request->only(['cancerType', 'result', 'dateFrom', 'dateTo', 'facilityId']);
        $query = $this->screenedQuery($visible, $rels, $filters);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('clientId', 'like', "%{$search}%")
                  ->orWhere('fullName', 'like', "%{$search}%")
                  ->orWhere('phoneNumber', 'like', "%{$search}%")
                  ->orWhere('hospitalNumber', 'like', "%{$search}%");
            });
        }

        $lab = $request->query('lab');
        if ($lab) {
            $query->whereIn('clientId', $this->labClients($lab));
        }

        $page = $query
            ->withMax('visits', 'visitId')
            ->with(['visits' => fn ($v) => $v->with(array_values($rels))])
            ->orderByDesc('visits_max_visitId')
            ->paginate(min((int) $request->query('perPage', 20), 100));

        $ids = collect($page->items())->pluck('clientId');
        $visitToClient = collect($page->items())->flatMap(fn ($c) => $c->visits->mapWithKeys(fn ($v) => [$v->visitId => $c->clientId]));
        $samples = ScreeningSample::query()
            ->where(function ($q) use ($ids, $visitToClient) {
                $q->whereIn('clientId', $ids);
                if ($visitToClient->isNotEmpty()) $q->orWhereIn('visitId', $visitToClient->keys());
            })
            ->orderByDesc('sampleId')->get()
            ->groupBy(fn ($sm) => $visitToClient[$sm->visitId] ?? $sm->clientId);

        $items = collect($page->items());
        $facilityMap = Facility::whereIn('facilityId', $items->flatMap(fn ($c) => $c->visits->pluck('facilityId')->push($c->facilityId))->filter()->unique())
            ->get(['facilityId', 'facilityName', 'facilityState'])->keyBy('facilityId');
        $userMap = $this->userNames($items->flatMap(fn ($c) => $c->visits->pluck('createdBy')));

        $rows = collect($page->items())->map(function (Client $c) use ($rels, $samples, $facilityMap, $userMap) {
            $latest = [];
            foreach ($rels as $type => $rel) {
                $best = null;
                foreach ($c->visits as $visit) {
                    $rec = $visit->{$rel};
                    if (!$rec) continue;
                    $raw = $rec->screeningDate ?: $visit->visitDate;
                    $d = $raw ? substr((string) $raw, 0, 10) : '';
                    if (!$best || $d >= $best['d']) {
                        $best = ['d' => $d, 'rec' => $rec, 'visit' => $visit];
                    }
                }
                if ($best) {
                    $latest[] = [
                        'cancerType'      => $type,
                        'screeningDate'   => $best['d'] ?: null,
                        'screeningResult' => $this->findingFor($best['rec'], $best['visit']),
                    ];
                }
            }

            // Where and by whom: the most recent visit, plus how many others.
            $latestVisit = $c->visits->sortByDesc('visitId')->first();
            $facIds = $c->visits->pluck('facilityId')->filter()->unique();
            $fid = $latestVisit?->facilityId ?: $c->facilityId;
            $fac = $fid ? ($facilityMap[$fid] ?? null) : null;
            $screenerIds = $c->visits->pluck('createdBy')->filter()->unique();
            $screenerId = $latestVisit?->createdBy;

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
                'screenedAt'     => $fac ? [
                    'facilityId'    => $fac->facilityId,
                    'facilityName'  => $fac->facilityName,
                    'facilityState' => $fac->facilityState ?? null,
                ] : null,
                'otherFacilities' => max($facIds->count() - 1, 0),
                'screenedBy'     => $screenerId ? ($userMap[$screenerId] ?? 'Unknown user') : null,
                'otherScreeners' => max($screenerIds->count() - 1, 0),
                'lastScreeningDate' => $dates->last(),
                'screenings'     => $latest,
                'tests' => $mine->take(4)->map(fn ($sm) => [
                    'testType'   => $sm->testType,
                    'cancerType' => $sm->cancerType,
                    'status'     => $sm->status,
                    'result'     => $sm->result,
                    'severity'   => $sm->resultSeverity,
                    'date'       => $sm->resultDate ? substr((string) $sm->resultDate, 0, 10) : ($sm->sampleDate ? substr((string) $sm->sampleDate, 0, 10) : null),
                ])->values(),
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
            'version' => 'screened-clients-v5',
            'clients' => $rows,
            'total'   => $page->total(),
            'page'    => $page->currentPage(),
            'perPage' => $page->perPage(),
            // Ignore the facility filter here so all facilities stay visible for comparison.
            'scope' => ($visible !== null && count($visible) === 1) ? 'facility' : 'multi',
            'screenerAnalytics' => $this->screenerAnalytics($visible, $rels, $filters, $search, $lab),
            'facilityAnalytics' => $this->facilityAnalytics($visible, $rels, array_diff_key($filters, ['facilityId' => 1]), $search, $lab),
            'summary' => [
                'screened'   => (clone $all)->count(),
                'suspicious' => $this->screenedQuery($visible, $rels, ['result' => 'suspicious'])->count(),
                'awaiting'   => $scope(ScreeningSample::query())->where('status', 'collected')->distinct()->count('clientId'),
                'urgentLabs' => $scope(ScreeningSample::query())->where('status', 'result_released')->where('resultSeverity', 'urgent')->distinct()->count('clientId'),
            ],
        ]);
    }
}
