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

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->dashboardService->getDashboardStats();

            return response()->json([
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent screening activity
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 6);

            $result = $this->dashboardService->getRecentActivity($page, $limit);

            return response()->json([
                'data' => $result['data'],
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to fetch recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly trends (optional - for the chart)
     */
    public function getMonthlyTrends(): JsonResponse
    {
        try {
            $trends = $this->dashboardService->getMonthlyTrends();

            return response()->json([
                'trends' => $trends
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
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);
            $offset = ($page - 1) * $limit;

            $referrals = \App\Models\CaseOutcome::with('client')
                ->where('cancerConfirmed', true)
                ->where(function ($query) {
                    $query->where('linkageToTreatment', false)
                        ->orWhereNull('linkageToTreatment');
                })
                ->orderBy('diagnosisDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $total = \App\Models\CaseOutcome::where('cancerConfirmed', true)
                ->where(function ($query) {
                    $query->where('linkageToTreatment', false)
                        ->orWhereNull('linkageToTreatment');
                })
                ->count();

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
     * Get screenings by type
     */
    public function getScreeningsByType(Request $request, string $type): JsonResponse
    {
        try {
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
                ->whereHas('visit.client');

            // Apply search filter
            if ($search) {
                $query->whereHas('visit.client', function ($q) use ($search) {
                    $q->where('fullName', 'like', "%{$search}%")
                      ->orWhere('screeningId', 'like', "%{$search}%");
                });
            }

            $total = $query->count();

            $screenings = $query->orderBy('screeningDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($screening) {
                    return [
                        'screeningId' => $screening->screeningId,
                        'visitId' => $screening->visitId,
                        'clientId' => $screening->visit->client->clientId ?? null,
                        'screeningDate' => $screening->screeningDate,
                        'method' => $screening->method,
                        'result' => $screening->result,
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
                            'screeningId' => $screening->visit->client->screeningId ?? '—',
                            'screening_id' => $screening->visit->client->screeningId ?? '—',
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
     * Get all positive findings
     */
    public function getPositiveFindings(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $limit = $request->input('limit', 10);
            $offset = ($page - 1) * $limit;

            $query = \App\Models\CaseOutcome::with('client')
                ->where('cancerConfirmed', true);

            if ($search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where('fullName', 'like', "%{$search}%")
                      ->orWhere('screeningId', 'like', "%{$search}%");
                });
            }

            $findings = $query->orderBy('diagnosisDate', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $total = \App\Models\CaseOutcome::where('cancerConfirmed', true)
                ->when($search, function ($q) use ($search) {
                    return $q->whereHas('client', function ($query) use ($search) {
                        $query->where('fullName', 'like', "%{$search}%")
                              ->orWhere('screeningId', 'like', "%{$search}%");
                    });
                })
                ->count();

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