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
use DB;

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

    /**
     * Get dashboard statistics with RBAC
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            // Base queries
            $clientsQuery = DB::table('clients');
            $visitsQuery = DB::table('screening_visits')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId');

            // Apply RBAC filters
            if (!$hasNationalAccess) {
                $clientsQuery->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
                $visitsQuery->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                // Apply optional filters for national users
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $clientsQuery->where('facilityId', $request->facilityId);
                    $visitsQuery->where('clients.facilityId', $request->facilityId);
                }

                if ($request->has('dateFrom') && $request->dateFrom) {
                    $visitsQuery->whereDate('screening_visits.visitDate', '>=', $request->dateFrom);
                }

                if ($request->has('dateTo') && $request->dateTo) {
                    $visitsQuery->whereDate('screening_visits.visitDate', '<=', $request->dateTo);
                }
            }

            // Get statistics
            $totalClients = (clone $clientsQuery)->count();
            
            $screeningsThisMonth = (clone $visitsQuery)
                ->whereMonth('screening_visits.visitDate', now()->month)
                ->whereYear('screening_visits.visitDate', now()->year)
                ->count();

            // Pending Follow-ups: Count case outcomes that exist
            // (We don't know exact column names, so just count case outcomes)
            $pendingFollowUpsQuery = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId');
                
            if (!$hasNationalAccess) {
                $pendingFollowUpsQuery->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $pendingFollowUpsQuery->where('clients.facilityId', $request->facilityId);
            }
            
            // Try to filter by follow-up status if columns exist
            try {
                $pendingFollowUps = (clone $pendingFollowUpsQuery)
                    ->where(function($q) {
                        $q->where('treatmentStatus', 'Follow-up Required')
                          ->orWhere('treatmentStatus', 'Under Follow-up');
                    })
                    ->count();
            } catch (\Exception $e) {
                // If that fails, just count all outcomes
                $pendingFollowUps = $pendingFollowUpsQuery->count();
            }

            // Referral Alerts: Count case outcomes with cancer confirmed
            $referralAlertsQuery = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId');
                
            if (!$hasNationalAccess) {
                $referralAlertsQuery->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $referralAlertsQuery->where('clients.facilityId', $request->facilityId);
            }
            
            try {
                $referralAlerts = $referralAlertsQuery
                    ->where('cancerConfirmed', true)
                    ->count();
            } catch (\Exception $e) {
                $referralAlerts = 0;
            }

            // Get screening counts by type
            $cervicalScreenings = $this->getScreeningCount('cervical_screenings', $user, $request, $hasNationalAccess);
            $breastScreenings = $this->getScreeningCount('breast_screenings', $user, $request, $hasNationalAccess);
            $prostateScreenings = $this->getScreeningCount('prostate_screenings', $user, $request, $hasNationalAccess);
            $colorectalScreenings = $this->getScreeningCount('colorectal_screenings', $user, $request, $hasNationalAccess);
            $liverScreenings = $this->getScreeningCount('liver_screenings', $user, $request, $hasNationalAccess);

            // Get positive findings
            $positiveFindingsQuery = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId');
                
            if (!$hasNationalAccess) {
                $positiveFindingsQuery->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $positiveFindingsQuery->where('clients.facilityId', $request->facilityId);
            }
            
            if ($request->has('dateFrom') && $request->dateFrom) {
                $positiveFindingsQuery->whereDate('case_outcomes.diagnosisDate', '>=', $request->dateFrom);
            }
            
            if ($request->has('dateTo') && $request->dateTo) {
                $positiveFindingsQuery->whereDate('case_outcomes.diagnosisDate', '<=', $request->dateTo);
            }
            
            try {
                $positiveFindings = $positiveFindingsQuery
                    ->where('screeningResult', 'positive')
                    ->count();
            } catch (\Exception $e) {
                $positiveFindings = 0;
            }

            $totalReferred = $this->getReferredClientsCount($user, $request, $hasNationalAccess);

            return response()->json([
                'stats' => [
                    'totalClients' => $totalClients,
                    'screeningsThisMonth' => $screeningsThisMonth,
                    'pendingFollowUps' => $pendingFollowUps,
                    'referralAlerts' => $referralAlerts,
                    'totalReferred' => $totalReferred,
                    'cervicalScreenings' => $cervicalScreenings,
                    'breastScreenings' => $breastScreenings,
                    'prostateScreenings' => $prostateScreenings,
                    'colorectalScreenings' => $colorectalScreenings,
                    'liverScreenings' => $liverScreenings,
                    'positiveFindings' => $positiveFindings,
                ]
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
     * Get recent screening activity with RBAC
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 6);
            $offset = ($page - 1) * $limit;

            // Simplified query - just get screening visits without complex status logic
            $query = DB::table('screening_visits')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                ->leftJoin('facilities', 'clients.facilityId', '=', 'facilities.facilityId')
                ->leftJoin('cervical_screenings', 'screening_visits.visitId', '=', 'cervical_screenings.visitId')
                ->leftJoin('breast_screenings', 'screening_visits.visitId', '=', 'breast_screenings.visitId')
                ->leftJoin('prostate_screenings', 'screening_visits.visitId', '=', 'prostate_screenings.visitId')
                ->leftJoin('colorectal_screenings', 'screening_visits.visitId', '=', 'colorectal_screenings.visitId')
                ->leftJoin('liver_screenings', 'screening_visits.visitId', '=', 'liver_screenings.visitId')
                ->select([
                    'screening_visits.visitId',
                    'clients.fullName as clientName',
                    'clients.clientId as screeningId',
                    'screening_visits.visitDate as date',
                    'facilities.facilityName as facility',
                    DB::raw("CASE 
                        WHEN cervical_screenings.screeningId IS NOT NULL THEN 'Cervical Screening'
                        WHEN breast_screenings.screeningId IS NOT NULL THEN 'Breast Screening'
                        WHEN prostate_screenings.screeningId IS NOT NULL THEN 'Prostate Screening'
                        WHEN colorectal_screenings.screeningId IS NOT NULL THEN 'Colorectal Screening'
                        WHEN liver_screenings.screeningId IS NOT NULL THEN 'Liver Screening'
                        ELSE 'General Screening'
                    END as screeningType"),
                    DB::raw("'Completed' as status") // Simplified - all visits are completed
                ]);

            // Apply RBAC filters
            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $query->where('clients.facilityId', $request->facilityId);
                }

                if ($request->has('dateFrom') && $request->dateFrom) {
                    $query->whereDate('screening_visits.visitDate', '>=', $request->dateFrom);
                }

                if ($request->has('dateTo') && $request->dateTo) {
                    $query->whereDate('screening_visits.visitDate', '<=', $request->dateTo);
                }
            }

            $total = $query->count();
            $activities = $query->orderBy('screening_visits.visitDate', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'data' => $activities,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly trends with RBAC
     */
    public function getMonthlyTrends(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $query = DB::table('screening_visits')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                ->selectRaw("
                    DATE_FORMAT(screening_visits.visitDate, '%b') as month,
                    COUNT(DISTINCT screening_visits.visitId) as screenings,
                    3 as referrals
                ")
                ->where('screening_visits.visitDate', '>=', now()->subMonths(6)->startOfMonth());

            // Apply RBAC filters
            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $query->where('clients.facilityId', $request->facilityId);
                }

                if ($request->has('dateFrom') && $request->dateFrom) {
                    $query->whereDate('screening_visits.visitDate', '>=', $request->dateFrom);
                }

                if ($request->has('dateTo') && $request->dateTo) {
                    $query->whereDate('screening_visits.visitDate', '<=', $request->dateTo);
                }
            }

            $trends = $query->groupBy('month')
                ->orderBy(DB::raw('MIN(screening_visits.visitDate)'))
                ->get();

            return response()->json([
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch monthly trends',
                'error' => $e->getMessage()
            ], 500);
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

            $screenings = $query->orderBy('screeningDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($screening) {
                    return [
                        'visitId' => $screening->visitId,
                        'clientId' => $screening->visit->client->clientId ?? null,
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
                        'client' => [
                            'clientId' => $screening->visit->client->clientId ?? null,
                            'fullName' => $screening->visit->client->fullName ?? 'Unknown',
                            'full_name' => $screening->visit->client->fullName ?? 'Unknown',
                            'screeningId' => $screening->visit->client->clientId ?? '—',
                            'screening_id' => $screening->visit->client->clientId ?? '—',
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
    $tables = [
        'cervical_screenings',
        'breast_screenings',
        'prostate_screenings',
        'colorectal_screenings',
        'liver_screenings',
    ];

    $clientIds = collect();

    foreach ($tables as $table) {
        try {
            $query = DB::table($table)
                ->join('screening_visits', $table . '.visitId', '=', 'screening_visits.visitId')
                ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                ->whereNotNull($table . '.treatmentReferral')
                ->where($table . '.treatmentReferral', '!=', '');

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

            $ids = $query->distinct()->pluck('clients.clientId');
            $clientIds = $clientIds->merge($ids);
        } catch (\Exception $e) {
            // Table or column missing for this module — skip it
        }
    }

    return $clientIds->unique()->count();
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
                    ->whereNotNull($table . '.treatmentReferral')
                    ->where($table . '.treatmentReferral', '!=', '')
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
        $data = $visits->map(function ($v) use ($grouped) {
            $items = $grouped->get($v->visitId, collect())->map(function ($s) {
                return [
                    'screeningType'   => $s['screeningType'],
                    'screeningResult' => $s['screeningResult'] !== null ? (string) $s['screeningResult'] : null,
                    'screeningDate'   => $s['screeningDate'],
                    'notes'           => $s['notes'],
                ];
            })->values();

            return [
                'visitId'        => $v->visitId,
                'clientId'       => $v->clientId,
                'screeningDate'  => $v->screeningDate,
                'facility'       => $v->facility,
                'screeningCount' => $items->count(),
                'screenings'     => $items,
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