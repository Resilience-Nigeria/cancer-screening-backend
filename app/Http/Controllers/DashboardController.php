<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Models\CervicalScreening;
use App\Models\BreastScreening;
use App\Models\ProstateScreening;
use App\Models\ColorectalScreening;
use App\Models\LiverScreening;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Referrals;
use App\Support\ClientFindings;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Check if user has national access — delegates entirely to the
     * User model, which reads this from the role's configured
     * dataScopeType. No hardcoded role list here anymore.
     */
    private function hasNationalAccess($user): bool
    {
        return $user->hasNationalAccess();
    }

    /** cancer type => screening table */
    private const MODULES = [
        'cervical'   => 'cervical_screenings',
        'breast'     => 'breast_screenings',
        'prostate'   => 'prostate_screenings',
        'colorectal' => 'colorectal_screenings',
        'liver'      => 'liver_screenings',
    ];

    /** Same grouping as Screened Clients / All Test Results: positive = suspicious, abnormal = suspicious. */
    private const SUSPICIOUS_FINDINGS = ['suspicious', 'positive'];
    private const SUSPICIOUS_SEVERITIES = ['urgent', 'abnormal'];

    /**
     * Facility limit for this request: null = no limit (national, no facility filter),
     * otherwise the list of facility ids to include.
     */
    private function facilityScope($user, Request $request, bool $national): ?array
    {
        if (!$national) {
            return $user->visibleFacilityIds() ?? [];
        }
        if ($request->filled('facilityId') && $request->facilityId !== 'all') {
            return [$request->facilityId];
        }
        return null;
    }

    /** Lab samples (soft-deleted ones excluded), limited to the facility scope. */
    private function samples(?array $facilities)
    {
        static $hasDeletedAt = null;
        $hasDeletedAt ??= Schema::hasColumn('screening_samples', 'deletedAt');

        return DB::table('screening_samples')
            ->when($hasDeletedAt, fn ($q) => $q->whereNull('screening_samples.deletedAt'))
            ->when($facilities !== null, fn ($q) => $q->whereIn('screening_samples.facilityId', $facilities));
    }

    /** Client ids with a screening record of this module (screening date in range), within scope. */
    private function moduleClients(string $table, ?array $facilities, ?string $from, ?string $to, ?callable $extra = null)
    {
        return DB::table($table)
            ->join('screening_visits', $table . '.visitId', '=', 'screening_visits.visitId')
            ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
            ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
            ->when($from, fn ($q) => $q->whereDate(DB::raw("COALESCE({$table}.screeningDate, screening_visits.visitDate)"), '>=', $from))
            ->when($to, fn ($q) => $q->whereDate(DB::raw("COALESCE({$table}.screeningDate, screening_visits.visitDate)"), '<=', $to))
            ->when($extra, $extra)
            ->distinct()
            ->pluck('clients.clientId');
    }

    /**
     * Get dashboard statistics with RBAC.
     * Counts follow the same rules as the Screened Clients and All Test Results pages:
     * a client counts as screened for a cancer type with a screening record OR a lab test of that type,
     * and a finding is suspicious when the form says suspicious/positive OR a released lab result is suspicious.
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $national = $this->hasNationalAccess($user);
            $facilities = $this->facilityScope($user, $request, $national);
            $from = $national && $request->filled('dateFrom') ? $request->dateFrom : null;
            $to   = $national && $request->filled('dateTo') ? $request->dateTo : null;

            // Registered clients
            $totalClients = DB::table('clients')
                ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
                ->count();

            // Clients screened per cancer type and suspicious clients — same rules as Screened Clients
            // (form result, else HPV result, else the visit outcome; lab results included).
            $findings = ClientFindings::build($facilities, $from, $to);
            $perType = [];
            foreach (array_keys(self::MODULES) as $type) {
                $perType[$type] = count(ClientFindings::screenedIds($findings, $type));
            }
            $screenedTotal = count(ClientFindings::screenedIds($findings));
            $suspiciousFindings = count(array_filter($findings, fn ($c) => ClientFindings::isSuspicious($c)));

            // Screened this month: distinct clients with a screening or a sample taken this month.
            $screeningsThisMonth = count(ClientFindings::screenedIds(ClientFindings::build(
                $facilities, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
            )));

            // Pending follow-ups (unchanged rule: case outcomes still under follow-up).
            try {
                $pendingFollowUps = DB::table('case_outcomes')
                    ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                    ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
                    ->whereIn('treatmentStatus', ['Follow-up Required', 'Under Follow-up'])
                    ->count();
            } catch (\Exception $e) {
                $pendingFollowUps = 0;
            }

            // Referrals waiting for action at my facilities (the Linked Clients inbox).
            try {
                // Same rule as Referred Clients → Incoming → Active (same-facility referrals included).
                $referralAlerts = DB::table('client_referrals')
                    ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
                    ->whereNotNull('client_referrals.toFacilityId')
                    ->whereIn('client_referrals.status', ['pending', 'accepted'])
                    ->when($facilities !== null, fn ($q) => $q->whereIn('client_referrals.toFacilityId', $facilities))
                    ->count();
            } catch (\Exception $e) {
                $referralAlerts = 0;
            }

            // Clients referred onwards: a referral record sent from my facilities, or "Referred" on a screening form.
            // 'Not referred' on the form is not counted.
            try {
                $totalReferred = Referrals::referredClientCount($facilities, $from, $to);
                $referredFormOnly = Referrals::formOnly($facilities, $from, $to)->count();
            } catch (\Exception $e) {
                $totalReferred = 0;
                $referredFormOnly = 0;
            }

            // Stage 4 — active treatment plans
            $activeTreatmentPlans = DB::table('treatment_plans')->where('status', 'active')
                ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
                ->count();

            // ---- Lab & data quality (same rules as the Screened Clients warnings) ----
            $awaitingResults = $this->samples($facilities)->where('status', 'collected')->count();
            $overdueResults = $this->samples($facilities)->where('status', 'collected')
                ->whereDate('sampleDate', '<=', now()->subDays(7)->toDateString())->count();
            $bookedTests = $this->samples($facilities)->where('status', 'booked')
                ->whereDate('sampleDate', '>=', now()->toDateString())->count();

            $duplicateClients = DB::query()->fromSub(
                $this->samples($facilities)->where('status', 'result_released')
                    ->select('clientId')
                    ->groupBy('clientId', 'testType', DB::raw('DATE(sampleDate)'))
                    ->havingRaw('COUNT(*) > 1'),
                'd'
            )->distinct()->count('clientId');

            // Result on the screening form, but no sample / test of that cancer type in the testing module.
            $noSample = collect();
            foreach (self::MODULES as $type => $table) {
                try {
                    $noSample = $noSample->merge($this->moduleClients($table, $facilities, null, null,
                        fn ($q) => $q->whereNotNull($table . '.screeningResult')
                            ->whereNotIn('clients.clientId', DB::table('screening_samples')->select('clientId')->where('cancerType', $type))));
                } catch (\Exception $e) {
                }
            }
            $resultsWithoutSample = $noSample->unique()->count();

            // Deletion requests waiting for an admin (only reported to admins).
            $pendingDeletionRequests = null;
            if ($user->isSuperAdmin() || $user->isNicratStaff()
                || (method_exists($user, 'isHospitalAdminRole') && $user->isHospitalAdminRole())) {
                try {
                    $pendingDeletionRequests = DB::table('screening_sample_deletion_requests')
                        ->where('status', 'pending')
                        ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
                        ->count();
                } catch (\Exception $e) {
                    $pendingDeletionRequests = null;
                }
            }

            return response()->json([
                'stats' => [
                    'totalClients'         => $totalClients,
                    'screenedClients'      => $screenedTotal,
                    'screeningsThisMonth'  => $screeningsThisMonth,
                    'pendingFollowUps'     => $pendingFollowUps,
                    'referralAlerts'       => $referralAlerts,
                    'totalReferred'        => $totalReferred,
                    'referredFormOnly'     => $referredFormOnly,
                    'cervicalScreenings'   => $perType['cervical'],
                    'breastScreenings'     => $perType['breast'],
                    'prostateScreenings'   => $perType['prostate'],
                    'colorectalScreenings' => $perType['colorectal'],
                    'liverScreenings'      => $perType['liver'],
                    'suspiciousFindings'   => $suspiciousFindings,
                    'positiveFindings'     => $suspiciousFindings, // older screens
                    'activeTreatmentPlans' => $activeTreatmentPlans,
                    'awaitingResults'      => $awaitingResults,
                    'overdueResults'       => $overdueResults,
                    'bookedTests'          => $bookedTests,
                    'duplicateResults'     => $duplicateClients,
                    'resultsWithoutSample' => $resultsWithoutSample,
                    'pendingDeletionRequests' => $pendingDeletionRequests,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to get screening count by type
     */
    private function getScreeningCount(string $table, $user, Request $request, bool $hasNationalAccess): int
    {
        try {
            $query = DB::table($table)
                ->join('screening_visits', $table . '.visitId', '=', 'screening_visits.visitId')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId');

            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $query->where('clients.facilityId', $request->facilityId);
                }

                if ($request->has('dateFrom') && $request->dateFrom) {
                    $query->whereDate($table . '.screeningDate', '>=', $request->dateFrom);
                }

                if ($request->has('dateTo') && $request->dateTo) {
                    $query->whereDate($table . '.screeningDate', '<=', $request->dateTo);
                }
            }

            return $query->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Recent activity with RBAC: screening visits plus lab events (sample taken / result released / test booked),
     * newest first, paged on the server (?page= &limit=).
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $national = $this->hasNationalAccess($user);
            $facilities = $this->facilityScope($user, $request, $national);
            $from = $national && $request->filled('dateFrom') ? $request->dateFrom : null;
            $to   = $national && $request->filled('dateTo') ? $request->dateTo : null;

            $page = max(1, (int) $request->input('page', 1));
            $limit = min(max(1, (int) $request->input('limit', 6)), 50);
            $offset = ($page - 1) * $limit;

            $labels = ['cervical' => 'Cervical', 'breast' => 'Breast', 'prostate' => 'Prostate', 'colorectal' => 'Colorectal', 'liver' => 'Liver'];
            $typeExpr = "CONCAT_WS(', ', " . implode(', ', array_map(
                fn ($t, $tbl) => "IF(EXISTS(SELECT 1 FROM {$tbl} m WHERE m.visitId = screening_visits.visitId), '{$labels[$t]}', NULL)",
                array_keys(self::MODULES), self::MODULES
            )) . ")";

            $visits = DB::table('screening_visits')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                ->leftJoin('facilities', 'clients.facilityId', '=', 'facilities.facilityId')
                ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate('screening_visits.visitDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('screening_visits.visitDate', '<=', $to))
                ->selectRaw("
                    'visit' as kind,
                    screening_visits.visitId as refId,
                    clients.fullName as clientName,
                    clients.clientId as screeningId,
                    screening_visits.visitDate as date,
                    screening_visits.visitId as sortId,
                    facilities.facilityName as facility,
                    {$typeExpr} as detail,
                    NULL as testType,
                    'Screened' as status
                ");

            static $hasDeletedAt = null;
            $hasDeletedAt ??= Schema::hasColumn('screening_samples', 'deletedAt');
            $labs = DB::table('screening_samples')
                ->join('clients', 'screening_samples.clientId', '=', 'clients.clientId')
                ->leftJoin('facilities', 'screening_samples.facilityId', '=', 'facilities.facilityId')
                ->when($hasDeletedAt, fn ($q) => $q->whereNull('screening_samples.deletedAt'))
                ->when($facilities !== null, fn ($q) => $q->whereIn('screening_samples.facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate(DB::raw('COALESCE(screening_samples.resultDate, screening_samples.sampleDate)'), '>=', $from))
                ->when($to, fn ($q) => $q->whereDate(DB::raw('COALESCE(screening_samples.resultDate, screening_samples.sampleDate)'), '<=', $to))
                ->selectRaw("
                    'lab' as kind,
                    screening_samples.sampleId as refId,
                    clients.fullName as clientName,
                    clients.clientId as screeningId,
                    COALESCE(screening_samples.resultDate, screening_samples.sampleDate) as date,
                    screening_samples.sampleId as sortId,
                    facilities.facilityName as facility,
                    screening_samples.result as detail,
                    screening_samples.testType as testType,
                    CASE screening_samples.status
                        WHEN 'result_released' THEN (CASE WHEN screening_samples.resultSeverity IN ('urgent','abnormal') THEN 'Suspicious result' WHEN screening_samples.resultSeverity = 'normal' THEN 'Result released' ELSE 'Inconclusive' END)
                        WHEN 'booked' THEN 'Booked'
                        ELSE 'Awaiting result'
                    END as status
                ");

            $union = $visits->unionAll($labs);
            $total = DB::query()->fromSub($union, 'a')->count();
            $rows = DB::query()->fromSub($union, 'a')
                ->orderByDesc('date')->orderByDesc('sortId')
                ->offset($offset)->limit($limit)->get();

            $data = $rows->map(function ($r) {
                if ($r->kind === 'lab') {
                    $test = \App\Support\ScreeningTestCatalog::find($r->testType);
                    $label = $test['label'] ?? str_replace('_', ' ', (string) $r->testType);
                    $result = $r->detail && $test
                        ? (collect($test['results'] ?? [])->firstWhere('value', $r->detail)['label'] ?? $r->detail)
                        : null;
                    $type = $label . ($r->status === 'Booked' ? ' (booked)' : ($result ? " · {$result}" : ' · sample taken'));
                } else {
                    $type = $r->detail ? "{$r->detail} screening" : 'Visit (no screening saved yet)';
                }
                return [
                    'visitId'       => ($r->kind === 'lab' ? 'lab-' : 'visit-') . $r->refId,
                    'kind'          => $r->kind,
                    'clientName'    => $r->clientName,
                    'screeningId'   => $r->screeningId,
                    'date'          => $r->date,
                    'facility'      => $r->facility ?? '—',
                    'screeningType' => $type,
                    'status'        => $r->status,
                ];
            })->values();

            return response()->json([
                'data'  => $data,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monthly trend with RBAC: distinct clients screened (screening record or lab sample) and
     * distinct clients referred (referral record sent, or "Referred" on a screening form) per month.
     * Last 6 months by default; with dateFrom/dateTo, the months in that range (up to 24).
     */
    public function getMonthlyTrends(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $national = $this->hasNationalAccess($user);
            $facilities = $this->facilityScope($user, $request, $national);

            $start = now()->subMonths(5)->startOfMonth();
            $end = now()->endOfMonth();
            if ($national && $request->filled('dateFrom')) {
                $start = \Carbon\Carbon::parse($request->dateFrom)->startOfMonth();
            }
            if ($national && $request->filled('dateTo')) {
                $end = \Carbon\Carbon::parse($request->dateTo)->endOfMonth();
            }
            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfMonth(), $start->copy()->endOfMonth()];
            }
            if ($start->diffInMonths($end) > 23) {
                $start = $end->copy()->subMonths(23)->startOfMonth();
            }
            $from = $start->toDateString();
            $to = $end->toDateString();

            // month (Y-m) => [clientId => true]
            $screened = [];
            $referred = [];
            $add = function (array &$bucket, $rows) {
                foreach ($rows as $r) {
                    if ($r->d && $r->clientId) {
                        $bucket[substr((string) $r->d, 0, 7)][$r->clientId] = true;
                    }
                }
            };

            foreach (self::MODULES as $type => $table) {
                try {
                    $date = "COALESCE({$table}.screeningDate, screening_visits.visitDate)";
                    $add($screened, DB::table($table)
                        ->join('screening_visits', "{$table}.visitId", '=', 'screening_visits.visitId')
                        ->when($facilities !== null, fn ($q) => $q->whereIn('screening_visits.facilityId', $facilities))
                        ->whereBetween(DB::raw("DATE({$date})"), [$from, $to])
                        ->selectRaw("screening_visits.clientId as clientId, DATE({$date}) as d")
                        ->get());
                } catch (\Exception $e) {
                }
            }
            $add($screened, $this->samples($facilities)
                ->whereIn('status', ['collected', 'result_released'])
                ->whereBetween(DB::raw('DATE(sampleDate)'), [$from, $to])
                ->selectRaw('clientId, DATE(sampleDate) as d')
                ->get());

            $add($referred, DB::table('client_referrals')
                ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
                ->when($facilities !== null, function ($q) use ($facilities) {
                    $q->where(fn ($w) => $w->whereIn('client_referrals.fromFacilityId', $facilities)
                        ->orWhere(fn ($x) => $x->whereNull('client_referrals.fromFacilityId')->whereIn('clients.facilityId', $facilities)));
                })
                ->whereBetween(DB::raw('DATE(client_referrals.referralDate)'), [$from, $to])
                ->selectRaw('client_referrals.clientId as clientId, DATE(client_referrals.referralDate) as d')
                ->get());
            $add($referred, Referrals::formReferrals($facilities, $from, $to)
                ->map(fn ($r) => (object) ['clientId' => $r->clientId, 'd' => $r->screeningDate]));

            $multiYear = $start->year !== $end->year;
            $trends = [];
            for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
                $key = $m->format('Y-m');
                $trends[] = [
                    'month'      => $multiYear ? $m->format('M y') : $m->format('M'),
                    'key'        => $key,
                    'screenings' => count($screened[$key] ?? []),
                    'referrals'  => count($referred[$key] ?? []),
                ];
            }

            return response()->json(['data' => $trends]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch monthly trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Facility scope + date range shared by the analytics endpoints. */
    private function analyticsScope(Request $request): array
    {
        $user = Auth::user();
        $national = $this->hasNationalAccess($user);
        return [
            $this->facilityScope($user, $request, $national),
            $national && $request->filled('dateFrom') ? $request->dateFrom : null,
            $national && $request->filled('dateTo') ? $request->dateTo : null,
        ];
    }

    /**
     * GET /analytics/lab-findings
     * Findings per cancer type (client-level, ClientFindings rules — the same as Screened Clients),
     * per-test counts, overdue samples (> 7 days) and the average days from sample to result.
     */
    public function getLabAnalytics(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->analyticsScope($request);

            $findings = ClientFindings::build($facilities, $from, $to);
            $byCancerType = [];
            foreach (array_keys(self::MODULES) as $type) {
                $row = ['cancerType' => $type, 'screened' => 0, 'suspicious' => 0, 'non_suspicious' => 0,
                        'inconclusive' => 0, 'awaiting' => 0, 'no_result' => 0];
                foreach ($findings as $c) {
                    if (!array_key_exists($type, $c['types'])) continue;
                    $row['screened']++;
                    $row[$c['types'][$type] ?? 'no_result']++;
                }
                $byCancerType[] = $row;
            }

            $samples = $this->samples($facilities)
                ->whereIn('status', ['collected', 'result_released'])
                ->when($from, fn ($q) => $q->whereDate('sampleDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('sampleDate', '<=', $to))
                ->select('clientId', 'cancerType', 'testType', 'status', 'resultSeverity', 'sampleDate', 'resultDate')
                ->get();

            $overdueCutoff = now()->subDays(7)->toDateString();
            $tests = [];
            $turnaround = [];
            foreach ($samples as $s) {
                $t = &$tests[$s->testType];
                $t ??= ['testType' => $s->testType, 'cancerType' => $s->cancerType, 'total' => 0, 'awaiting' => 0, 'overdue' => 0,
                        'suspicious' => 0, 'non_suspicious' => 0, 'inconclusive' => 0, 'days' => []];
                $t['total']++;
                if ($s->status === 'result_released') {
                    $t[ClientFindings::fromSeverity($s->resultSeverity)]++;
                    if ($s->sampleDate && $s->resultDate) {
                        $d = max(0, (int) floor((strtotime(substr($s->resultDate, 0, 10)) - strtotime(substr($s->sampleDate, 0, 10))) / 86400));
                        $t['days'][] = $d;
                        $turnaround[] = $d;
                    }
                } else {
                    $t['awaiting']++;
                    if ($s->sampleDate && substr($s->sampleDate, 0, 10) <= $overdueCutoff) $t['overdue']++;
                }
                unset($t);
            }

            $testRows = collect($tests)->map(function ($t) {
                $t['avgTurnaroundDays'] = count($t['days']) ? round(array_sum($t['days']) / count($t['days']), 1) : null;
                unset($t['days']);
                return $t;
            })->sortByDesc('total')->values();

            return response()->json([
                'status' => true,
                'data' => [
                    'byCancerType' => $byCancerType,
                    'tests'        => $testRows,
                    'totals'       => [
                        'samples'           => $samples->count(),
                        'released'          => $samples->where('status', 'result_released')->count(),
                        'awaiting'          => $samples->where('status', 'collected')->count(),
                        'overdue'           => $testRows->sum('overdue'),
                        'avgTurnaroundDays' => count($turnaround) ? round(array_sum($turnaround) / count($turnaround), 1) : null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unable to fetch lab analytics', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /analytics/stage2-summary
     * One result per screened client (the most serious):
     *   urgent_referral  a visit outcome of Urgent referral
     *   suspicious       a suspicious finding on any form or lab test, or a visit outcome of Suspicious
     *   low_suspicion    a visit outcome of Low suspicion
     *   normal           a non-suspicious finding, or a visit outcome of Normal
     *   inconclusive     only inconclusive lab results
     *   awaiting         a sample taken, no result yet
     *   not_recorded     screened, but no result and no outcome recorded
     * Also flags suspicious clients whose visit has no Suspicious / Urgent outcome recorded (so no referral was made).
     */
    public function getStage2Summary(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->analyticsScope($request);
            $findings = ClientFindings::build($facilities, $from, $to);

            $dist = ['normal' => 0, 'low_suspicion' => 0, 'suspicious' => 0, 'urgent_referral' => 0,
                     'inconclusive' => 0, 'awaiting' => 0, 'not_recorded' => 0];
            $suspiciousIds = [];
            $noOutcome = 0;
            foreach ($findings as $id => $c) {
                $types = $c['types'];
                if ($c['outcome'] === 'urgent_referral') {
                    $k = 'urgent_referral';
                } elseif (in_array('suspicious', $types, true) || $c['outcome'] === 'suspicious') {
                    $k = 'suspicious';
                } elseif ($c['outcome'] === 'low_suspicion') {
                    $k = 'low_suspicion';
                } elseif (in_array('non_suspicious', $types, true) || $c['outcome'] === 'normal') {
                    $k = 'normal';
                } elseif (in_array('inconclusive', $types, true)) {
                    $k = 'inconclusive';
                } elseif (in_array('awaiting', $types, true)) {
                    $k = 'awaiting';
                } else {
                    $k = 'not_recorded';
                }
                $dist[$k]++;
                if ($k === 'suspicious' || $k === 'urgent_referral') {
                    $suspiciousIds[] = $id;
                    if (!in_array($c['outcome'], ClientFindings::SUSPICIOUS_OUTCOMES, true)) $noOutcome++;
                }
            }

            // Suspicious clients who have been referred (a referral record or "Referred" on a screening form).
            $referred = 0;
            if ($suspiciousIds) {
                $referredIds = collect();
                foreach (array_chunk($suspiciousIds, 1000) as $chunk) {
                    $referredIds = $referredIds->merge(DB::table('client_referrals')->whereIn('clientId', $chunk)->distinct()->pluck('clientId'));
                    foreach (self::MODULES as $table) {
                        try {
                            $referredIds = $referredIds->merge(DB::table($table)
                                ->join('screening_visits', "{$table}.visitId", '=', 'screening_visits.visitId')
                                ->whereIn('screening_visits.clientId', $chunk)
                                ->where("{$table}.treatmentReferral", 'referred')
                                ->distinct()->pluck('screening_visits.clientId'));
                        } catch (\Exception $e) {
                        }
                    }
                }
                $referred = $referredIds->unique()->count();
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'distribution'        => $dist,
                    'total'               => array_sum($dist),
                    'suspiciousTotal'     => count($suspiciousIds),
                    'suspiciousNoOutcome' => $noOutcome,
                    'suspiciousReferred'  => $referred,
                    'suspiciousNotReferred' => count($suspiciousIds) - $referred,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unable to fetch Stage 2 summary', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /analytics/facility-summary
     * Per facility: clients screened (latest screening facility), suspicious clients, clients referred onwards,
     * lab samples awaiting / overdue.
     */
    public function getFacilitySummary(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->analyticsScope($request);
            $findings = ClientFindings::build($facilities, $from, $to);

            $rows = [];
            $row = function ($fid) use (&$rows) {
                $rows[$fid] ??= ['facilityId' => $fid, 'screened' => 0, 'suspicious' => 0, 'referred' => 0, 'awaiting' => 0, 'overdue' => 0];
            };
            foreach ($findings as $c) {
                if (empty($c['types']) || !$c['facilityId']) continue;
                $row($c['facilityId']);
                $rows[$c['facilityId']]['screened']++;
                if (ClientFindings::isSuspicious($c)) $rows[$c['facilityId']]['suspicious']++;
            }

            // Referred onwards: referral records by sending facility (client's facility when none recorded) + form referrals.
            $sent = DB::table('client_referrals')
                ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
                ->selectRaw('COALESCE(client_referrals.fromFacilityId, clients.facilityId) as fid, client_referrals.clientId as clientId')
                ->when($from, fn ($q) => $q->whereDate('client_referrals.referralDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('client_referrals.referralDate', '<=', $to))
                ->get()
                ->merge(Referrals::formReferrals(null, $from, $to)->map(fn ($r) => (object) ['fid' => $r->facilityId, 'clientId' => $r->clientId]));
            foreach ($sent->filter(fn ($r) => $r->fid && ($facilities === null || in_array($r->fid, $facilities)))->groupBy('fid') as $fid => $list) {
                $row($fid);
                $rows[$fid]['referred'] = $list->pluck('clientId')->unique()->count();
            }

            $cut = now()->subDays(7)->toDateString();
            $lab = $this->samples($facilities)->where('status', 'collected')
                ->selectRaw('facilityId, COUNT(*) as awaiting, SUM(CASE WHEN DATE(sampleDate) <= ? THEN 1 ELSE 0 END) as overdue', [$cut])
                ->groupBy('facilityId')->get();
            foreach ($lab as $l) {
                if (!$l->facilityId) continue;
                $row($l->facilityId);
                $rows[$l->facilityId]['awaiting'] = (int) $l->awaiting;
                $rows[$l->facilityId]['overdue'] = (int) $l->overdue;
            }

            $names = DB::table('facilities')->whereIn('facilityId', array_keys($rows))->pluck('facilityName', 'facilityId');
            $data = collect($rows)->map(function ($r) use ($names) {
                $r['facilityName'] = $names[$r['facilityId']] ?? 'Unknown facility';
                $r['suspiciousRate'] = $r['screened'] ? round($r['suspicious'] / $r['screened'] * 100, 1) : 0;
                return $r;
            })->sortByDesc('screened')->values();

            return response()->json(['status' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unable to fetch facility summary', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /analytics/journey-funnel
     * Cohort of clients registered in scope (registration date in range):
     * Registered → Screened → Suspicious → Referred → Stage 3 evaluation → Treatment plan.
     * Every later step only counts clients from the step before, so the funnel can't go up.
     */
    public function getJourneyFunnel(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->analyticsScope($request);
            $cols = array_values(array_filter(['registrationDate', 'createdAt', 'created_at'], fn ($c) => Schema::hasColumn('clients', $c)));
            $dateCol = count($cols) > 1 ? 'COALESCE(' . implode(', ', $cols) . ')' : ($cols[0] ?? null);
            if ($dateCol === null) {
                $from = $to = null; // no date column to filter on
            }

            $registered = DB::table('clients')
                ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate(DB::raw($dateCol), '>=', $from))
                ->when($to, fn ($q) => $q->whereDate(DB::raw($dateCol), '<=', $to))
                ->pluck('clientId')->flip();

            // Findings over all time and every facility, limited to this cohort.
            $findings = array_intersect_key(ClientFindings::build(null), $registered->all());
            $screened = array_filter($findings, fn ($c) => !empty($c['types']));
            $suspicious = array_filter($screened, fn ($c) => ClientFindings::isSuspicious($c));
            $suspIds = array_keys($suspicious);

            $referredIds = [];
            $evaluatedIds = [];
            $treatedIds = [];
            if ($suspIds) {
                $ref = collect();
                $evalTable = class_exists(\App\Models\DiagnosticEvaluation::class) ? (new \App\Models\DiagnosticEvaluation)->getTable() : null;
                $ev = collect();
                $tr = collect();
                foreach (array_chunk($suspIds, 1000) as $chunk) {
                    $ref = $ref->merge(DB::table('client_referrals')->whereIn('clientId', $chunk)->distinct()->pluck('clientId'));
                    foreach (self::MODULES as $table) {
                        try {
                            $ref = $ref->merge(DB::table($table)
                                ->join('screening_visits', "{$table}.visitId", '=', 'screening_visits.visitId')
                                ->whereIn('screening_visits.clientId', $chunk)
                                ->where("{$table}.treatmentReferral", 'referred')
                                ->distinct()->pluck('screening_visits.clientId'));
                        } catch (\Exception $e) {
                        }
                    }
                    if ($evalTable && Schema::hasTable($evalTable)) {
                        $ev = $ev->merge(DB::table($evalTable)->whereIn('clientId', $chunk)->distinct()->pluck('clientId'));
                    }
                    if (Schema::hasTable('treatment_plans')) {
                        $tr = $tr->merge(DB::table('treatment_plans')->whereIn('clientId', $chunk)->distinct()->pluck('clientId'));
                    }
                }
                $referredIds = $ref->unique()->values()->all();
                $evaluatedIds = array_values(array_intersect($referredIds, $ev->unique()->all()));
                $treatedIds = array_values(array_intersect($evaluatedIds, $tr->unique()->all()));
            }

            $steps = [
                ['key' => 'registered', 'label' => 'Registered', 'count' => $registered->count()],
                ['key' => 'screened', 'label' => 'Screened', 'count' => count($screened)],
                ['key' => 'suspicious', 'label' => 'Suspicious finding', 'count' => count($suspicious)],
                ['key' => 'referred', 'label' => 'Referred', 'count' => count($referredIds)],
                ['key' => 'evaluated', 'label' => 'Stage 3 evaluation', 'count' => count($evaluatedIds)],
                ['key' => 'treated', 'label' => 'Treatment plan', 'count' => count($treatedIds)],
            ];
            foreach ($steps as $i => &$st) {
                $prev = $i ? $steps[$i - 1]['count'] : null;
                $st['conversionFromPrevious'] = $prev ? round($st['count'] / $prev * 100, 1) : null;
            }
            unset($st);

            return response()->json(['status' => true, 'data' => $steps]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Unable to fetch the journey funnel', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get referral alerts (confirmed cancer cases needing linkage)
     */
    public function getReferrals(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);
            $offset = ($page - 1) * $limit;

            $query = \App\Models\CaseOutcome::with('client')
                ->whereHas('client', function($q) use ($user, $hasNationalAccess) {
                    if (!$hasNationalAccess) {
                        $q->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
                    }
                });
            
            // Try to filter by cancer confirmed if column exists
            try {
                $query->where('cancerConfirmed', true);
            } catch (\Exception $e) {
                // If column doesn't exist, just get all outcomes
            }

            $total = $query->count();
            
            $referrals = $query->orderBy('diagnosisDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return response()->json([
                'data' => $referrals,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get screenings by type with RBAC
     */
    public function getScreeningsByType(Request $request, string $type): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);
            
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $limit = $request->input('limit', 10);
            $offset = ($page - 1) * $limit;

            // Map screening type to model
            $modelMap = [
                'cervical' => CervicalScreening::class,
                'breast' => BreastScreening::class,
                'prostate' => ProstateScreening::class,
                'colorectal' => ColorectalScreening::class,
                'liver' => LiverScreening::class,
            ];

            if (!isset($modelMap[$type])) {
                return response()->json(['message' => 'Invalid screening type'], 400);
            }

            $modelClass = $modelMap[$type];

            // Build query with relationships
            $query = $modelClass::with(['visit.client'])
                ->whereHas('visit.client', function($q) use ($user, $hasNationalAccess) {
                    if (!$hasNationalAccess) {
                        $q->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
                    }
                });

            // Apply search filter
            if ($search) {
                $query->whereHas('visit.client', function ($q) use ($search) {
                    $q->where('fullName', 'like', "%{$search}%")
                      ->orWhere('clientId', 'like', "%{$search}%");
                });
            }

            $total = $query->count();

            $screeningResults = $query->orderBy('screeningDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $typeClientIds = $screeningResults->pluck('visit.client.clientId')->filter()->unique()->values();

            $stage3Referrals = \App\Models\ClientReferral::whereIn('clientId', $typeClientIds)
                ->where('referralType', 'screening_to_confirmation')
                ->orderByDesc('referralId')
                ->get()
                ->unique('clientId')
                ->keyBy('clientId');

            $stage3Evaluations = \App\Models\DiagnosticEvaluation::whereIn('clientId', $typeClientIds)
                ->orderByDesc('evaluationId')
                ->get()
                ->unique('clientId')
                ->keyBy('clientId');

            $screenings = $screeningResults
                ->map(function ($screening) use ($stage3Referrals, $stage3Evaluations) {
                    $clientId = $screening->visit->client->clientId ?? null;
                    $referral = $clientId ? $stage3Referrals->get($clientId) : null;
                    $evaluation = $clientId ? $stage3Evaluations->get($clientId) : null;

                    return [
                        'visitId' => $screening->visitId,
                        'clientId' => $clientId,
                        'screeningDate' => $screening->screeningDate,
                        'method' => $screening->method ?? null,
                        'result' => $screening->result ?? null,
                        'viaResult' => $screening->viaResult ?? null,
                        'cbeResult' => $screening->cbeResult ?? null,
                        'hpvResult' => $screening->hpvResult ?? null,
                        'psaLevel' => $screening->psaLevel ?? null,
                        'dreResult' => $screening->dreResult ?? null,
                        'notes' => $screening->notes ?? null,
                        'treatmentProvided' => $screening->treatmentProvided ?? null,
                        'treatmentReferral' => $screening->treatmentReferral ?? null,
                        'biopsyDone' => $screening->biopsyDone ?? null,
                        'biopsyResult' => $screening->biopsyResult ?? null,
                        'created_at' => $screening->created_at,
                        'stage3Referral' => $referral ? [
                            'referralId' => $referral->referralId,
                            'status' => $referral->status,
                        ] : null,
                        'stage3Evaluation' => $evaluation ? [
                            'evaluationId' => $evaluation->evaluationId,
                            'status' => $evaluation->status,
                            'decisionPathway' => $evaluation->decisionPathway,
                        ] : null,
                        'client' => [
                            'clientId' => $clientId,
                            'fullName' => $screening->visit->client->fullName ?? 'Unknown',
                            'full_name' => $screening->visit->client->fullName ?? 'Unknown',
                            'screeningId' => $clientId ?? '—',
                            'screening_id' => $clientId ?? '—',
                        ],
                    ];
                });

            return response()->json([
                'data' => $screenings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch screenings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all positive findings with RBAC
     */
    public function getPositiveFindings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);
            
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $limit = $request->input('limit', 10);
            $offset = ($page - 1) * $limit;

            $query = \App\Models\CaseOutcome::with('client')
                ->whereHas('client', function($q) use ($user, $hasNationalAccess) {
                    if (!$hasNationalAccess) {
                        $q->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
                    }
                });
            
            // Try to filter by screening result if column exists
            try {
                $query->where('screeningResult', 'positive');
            } catch (\Exception $e) {
                // Column doesn't exist, just get all outcomes
            }

            if ($search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where('fullName', 'like', "%{$search}%")
                      ->orWhere('clientId', 'like', "%{$search}%");
                });
            }

            $total = (clone $query)->count();
            
            $findings = $query->orderBy('diagnosisDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return response()->json([
                'data' => $findings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch positive findings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Count distinct clients who have been referred for treatment
 * across any screening module (treatmentReferral set).
 */
private function getReferredClientsCount($user, Request $request, bool $hasNationalAccess): int
{
    $facilities = $this->facilityScope($user, $request, $hasNationalAccess);
    $from = $hasNationalAccess && $request->filled('dateFrom') ? $request->dateFrom : null;
    $to = $hasNationalAccess && $request->filled('dateTo') ? $request->dateTo : null;
    return Referrals::referredClientCount($facilities, $from, $to);
}



/**
 * Get all referred clients (distinct clients with a treatment referral
 * recorded on any screening module), with RBAC + optional filters.
 */
public function getReferredClients(Request $request): JsonResponse
{
    try {
        $user = Auth::user();
        $hasNationalAccess = $this->hasNationalAccess($user);

        $page = (int) $request->input('page', 1);
        $search = $request->input('search', '');
        $limit = (int) $request->input('limit', 10);
        $offset = ($page - 1) * $limit;

        $modules = [
            'cervical_screenings'   => 'Cervical Screening',
            'breast_screenings'     => 'Breast Screening',
            'prostate_screenings'   => 'Prostate Screening',
            'colorectal_screenings' => 'Colorectal Screening',
            'liver_screenings'      => 'Liver Screening',
        ];

        $referralRows = collect();

        foreach ($modules as $table => $label) {
            try {
                $query = DB::table($table)
                    ->join('screening_visits', $table . '.visitId', '=', 'screening_visits.visitId')
                    ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                    ->leftJoin('facilities', 'clients.facilityId', '=', 'facilities.facilityId')
                    ->where($table . '.treatmentReferral', 'referred') // 'not_referred' is not a referral
                    ->select(
                        'clients.clientId',
                        'clients.fullName as clientName',
                        'clients.clientId as clientCode',
                        'facilities.facilityName',
                        DB::raw("'" . $label . "' as screeningType"),
                        $table . '.treatmentReferral as treatmentReferral',
                        $table . '.screeningDate as screeningDate'
                    );

                if (!$hasNationalAccess) {
                    $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
                } else {
                    if ($request->has('facilityId') && $request->facilityId !== 'all') {
                        $query->where('clients.facilityId', $request->facilityId);
                    }
                    if ($request->has('dateFrom') && $request->dateFrom) {
                        $query->whereDate($table . '.screeningDate', '>=', $request->dateFrom);
                    }
                    if ($request->has('dateTo') && $request->dateTo) {
                        $query->whereDate($table . '.screeningDate', '<=', $request->dateTo);
                    }
                }

                $referralRows = $referralRows->merge($query->get());
            } catch (\Exception $e) {
                // Module table/column missing — skip it
            }
        }

        // Collapse to distinct clients, aggregating their referrals
        $clients = $referralRows
            ->groupBy('clientId')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'clientId'             => $first->clientId,
                    'clientName'           => $first->clientName,
                    'clientCode'           => $first->clientCode,
                    'facilityName'         => $first->facilityName,
                    'modules'              => $rows->pluck('screeningType')->unique()->values()->implode(', '),
                    'referralDestinations' => $rows->pluck('treatmentReferral')->filter()->unique()->values()->implode(', '),
                    'referralCount'        => $rows->count(),
                    'lastReferralDate'     => $rows->max('screeningDate'),
                ];
            })
            ->values();

        if ($search) {
            $needle = strtolower($search);
            $clients = $clients->filter(function ($c) use ($needle) {
                return str_contains(strtolower((string) $c['clientName']), $needle)
                    || str_contains(strtolower((string) $c['clientCode']), $needle);
            })->values();
        }

        $clients = $clients->sortByDesc('lastReferralDate')->values();

        $total = $clients->count();
        $paged = $clients->slice($offset, $limit)->values();

        return response()->json([
            'data'  => $paged,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Unable to fetch referred clients',
            'error'   => $e->getMessage(),
        ], 500);
    }
}



/**
 * Get all screening visits across modules with RBAC.
 * Returns one row per visit, each with a `screenings` array (a visit can
 * include multiple tests, each with its own screeningResult).
 */
public function allScreenings(Request $request): JsonResponse
{
    try {
        $user = Auth::user();
        $hasNationalAccess = $this->hasNationalAccess($user);

        $page = (int) $request->input('page', 1);
        $search = $request->input('search', '');
        $limit = (int) $request->input('limit', 10);
        $offset = ($page - 1) * $limit;
        $type = $request->input('type');

        $moduleTables = [
            'cervical'   => 'cervical_screenings',
            'breast'     => 'breast_screenings',
            'prostate'   => 'prostate_screenings',
            'colorectal' => 'colorectal_screenings',
            'liver'      => 'liver_screenings',
        ];

        // 1) One row per visit (no module joins here)
        $visitsQuery = DB::table('screening_visits')
            ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
            ->leftJoin('facilities', 'clients.facilityId', '=', 'facilities.facilityId')
            ->select([
                'screening_visits.visitId',
                'screening_visits.visitDate as screeningDate',
                'clients.clientId',
                'clients.fullName as clientName',
                'facilities.facilityName as facility',
            ]);

        if (!$hasNationalAccess) {
            $visitsQuery->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
        } else {
            if ($request->has('facilityId') && $request->facilityId !== 'all') {
                $visitsQuery->where('clients.facilityId', $request->facilityId);
            }
            if ($request->has('dateFrom') && $request->dateFrom) {
                $visitsQuery->whereDate('screening_visits.visitDate', '>=', $request->dateFrom);
            }
            if ($request->has('dateTo') && $request->dateTo) {
                $visitsQuery->whereDate('screening_visits.visitDate', '<=', $request->dateTo);
            }
        }

        if ($search) {
            $visitsQuery->where(function ($q) use ($search) {
                $q->where('clients.fullName', 'like', "%{$search}%")
                  ->orWhere('clients.clientId', 'like', "%{$search}%");
            });
        }

        // Optional type filter: keep only visits that include that test type
        if ($type && $type !== 'all' && isset($moduleTables[$type])) {
            $table = $moduleTables[$type];
            $visitsQuery->whereIn('screening_visits.visitId', function ($q) use ($table) {
                $q->select('visitId')->from($table);
            });
        }

        $total = (clone $visitsQuery)->count();

        $visits = $visitsQuery->orderBy('screening_visits.visitDate', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $visitIds = $visits->pluck('visitId')->all();
        $screeningClientIds = $visits->pluck('clientId')->filter()->unique()->values();

        // Batch-load Stage 2->3 referral and Stage 3 evaluation status
        // per client, matching the same pattern used for /visits and
        // /outcomes.
        $stage3Referrals = \App\Models\ClientReferral::whereIn('clientId', $screeningClientIds)
            ->where('referralType', 'screening_to_confirmation')
            ->orderByDesc('referralId')
            ->get()
            ->unique('clientId')
            ->keyBy('clientId');

        $stage3Evaluations = \App\Models\DiagnosticEvaluation::whereIn('clientId', $screeningClientIds)
            ->orderByDesc('evaluationId')
            ->get()
            ->unique('clientId')
            ->keyBy('clientId');

        // 2) Gather every screening for the visits on this page
        $screeningsByVisit = collect();

        if (!empty($visitIds)) {
            foreach ($moduleTables as $key => $table) {
                try {
                    $rows = DB::table($table)
                        ->whereIn($table . '.visitId', $visitIds)
                        ->get();

                    foreach ($rows as $row) {
                        $screeningsByVisit->push([
                            'visitId'         => $row->visitId,
                            'screeningType'   => $key,
                            'screeningResult' => $row->screeningResult ?? null,
                            'screeningDate'   => $row->screeningDate ?? null,
                            'notes'           => $row->notes ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Module table missing — skip it
                }
            }
        }

        $grouped = $screeningsByVisit->groupBy('visitId');

        // 3) Attach the screenings array to each visit
        $data = $visits->map(function ($v) use ($grouped, $stage3Referrals, $stage3Evaluations) {
            $items = $grouped->get($v->visitId, collect())->map(function ($s) {
                return [
                    'screeningType'   => $s['screeningType'],
                    'screeningResult' => $s['screeningResult'] !== null ? (string) $s['screeningResult'] : null,
                    'screeningDate'   => $s['screeningDate'],
                    'notes'           => $s['notes'],
                ];
            })->values();

            $referral = $stage3Referrals->get($v->clientId);
            $evaluation = $stage3Evaluations->get($v->clientId);

            return [
                'visitId'        => $v->visitId,
                'clientId'       => $v->clientId,
                'screeningDate'  => $v->screeningDate,
                'facility'       => $v->facility,
                'screeningCount' => $items->count(),
                'screenings'     => $items,
                'stage3Referral' => $referral ? [
                    'referralId' => $referral->referralId,
                    'status' => $referral->status,
                ] : null,
                'stage3Evaluation' => $evaluation ? [
                    'evaluationId' => $evaluation->evaluationId,
                    'status' => $evaluation->status,
                    'decisionPathway' => $evaluation->decisionPathway,
                ] : null,
                'client'         => [
                    'clientId'     => $v->clientId,
                    'fullName'     => $v->clientName ?? 'Unknown',
                    'full_name'    => $v->clientName ?? 'Unknown',
                    'screeningId'  => $v->clientId ?? '—',
                    'screening_id' => $v->clientId ?? '—',
                ],
            ];
        });

        return response()->json([
            'data'  => $data,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Unable to fetch screenings',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}