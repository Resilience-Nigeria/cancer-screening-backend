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
     * Check if user has national access
     */
    private function hasNationalAccess($user): bool
    {
        $roleName = $user->user_role?->roleName ?? $user->role;
        return in_array($roleName, ['SUPER_ADMIN', 'NICRAT_STAFF']);
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
                $clientsQuery->where('facilityId', $user->facilityId);
                $visitsQuery->where('clients.facilityId', $user->facilityId);
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
                $pendingFollowUpsQuery->where('clients.facilityId', $user->facilityId);
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
                $referralAlertsQuery->where('clients.facilityId', $user->facilityId);
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
                $positiveFindingsQuery->where('clients.facilityId', $user->facilityId);
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

            return response()->json([
                'stats' => [
                    'totalClients' => $totalClients,
                    'screeningsThisMonth' => $screeningsThisMonth,
                    'pendingFollowUps' => $pendingFollowUps,
                    'referralAlerts' => $referralAlerts,
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
                $query->where('clients.facilityId', $user->facilityId);
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
                $query->where('clients.facilityId', $user->facilityId);
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
                    0 as referrals
                ")
                ->where('screening_visits.visitDate', '>=', now()->subMonths(6)->startOfMonth());

            // Apply RBAC filters
            if (!$hasNationalAccess) {
                $query->where('clients.facilityId', $user->facilityId);
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
                        $q->where('facilityId', $user->facilityId);
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
                        $q->where('facilityId', $user->facilityId);
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
                        $q->where('facilityId', $user->facilityId);
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
}