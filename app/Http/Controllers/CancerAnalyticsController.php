<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CancerAnalyticsController extends Controller
{
    /**
     * Age classification ranges
     */
    private const AGE_GROUPS = [
        'infancy' => ['label' => 'Infancy', 'min' => 0, 'max' => 1, 'description' => '0-12 months'],
        'early_childhood' => ['label' => 'Early Childhood', 'min' => 1, 'max' => 5, 'description' => '1-5 years'],
        'middle_late_childhood' => ['label' => 'Middle & Late Childhood', 'min' => 6, 'max' => 11, 'description' => '6-11 years'],
        'adolescents' => ['label' => 'Adolescents', 'min' => 10, 'max' => 19, 'description' => '10-19 years'],
        'youth' => ['label' => 'Youth', 'min' => 15, 'max' => 24, 'description' => '15-24 years'],
        'young_adults' => ['label' => 'Young Adults', 'min' => 25, 'max' => 44, 'description' => '25-44 years'],
        'middle_age' => ['label' => 'Middle Age', 'min' => 45, 'max' => 59, 'description' => '45-59 years'],
        'elderly' => ['label' => 'Elderly', 'min' => 60, 'max' => 74, 'description' => '60-74 years'],
        'senior' => ['label' => 'Senior', 'min' => 75, 'max' => 120, 'description' => '75+ years'],
    ];

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
     * Calculate age from date of birth
     */
    private function calculateAge($dateOfBirth): int
    {
        if (!$dateOfBirth) return 0;
        return Carbon::parse($dateOfBirth)->age;
    }

    /**
     * Get age classification for an age
     */
    private function getAgeClassification(int $age): ?string
    {
        // Check each age group
        foreach (self::AGE_GROUPS as $key => $group) {
            if ($age >= $group['min'] && $age <= $group['max']) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Get cancer types by age classification
     */
    public function getCancerByAgeClassification(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            // Build query for positive cancer cases
            $query = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.screeningResult', 'positive')
                ->whereNotNull('case_outcomes.cancerType')
                ->whereNotNull('clients.dateOfBirth')
                ->select([
                    'case_outcomes.cancerType',
                    'clients.dateOfBirth',
                    'clients.gender',
                    'case_outcomes.cancerStage',
                    'case_outcomes.diagnosisDate',
                ]);

            // Apply RBAC filters
            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $query->where('clients.facilityId', $request->facilityId);
                }
                if ($request->has('dateFrom') && $request->dateFrom) {
                    $query->whereDate('case_outcomes.diagnosisDate', '>=', $request->dateFrom);
                }
                if ($request->has('dateTo') && $request->dateTo) {
                    $query->whereDate('case_outcomes.diagnosisDate', '<=', $request->dateTo);
                }
            }

            $cases = $query->get();

            // Group by age classification and cancer type
            $ageGroupData = [];
            $cancerTypeStats = [];
            $totalCases = 0;

            foreach ($cases as $case) {
                $age = $this->calculateAge($case->dateOfBirth);
                $ageGroup = $this->getAgeClassification($age);
                
                if (!$ageGroup) continue;

                $cancerType = $case->cancerType;
                $totalCases++;

                // Initialize age group if not exists
                if (!isset($ageGroupData[$ageGroup])) {
                    $ageGroupData[$ageGroup] = [
                        'label' => self::AGE_GROUPS[$ageGroup]['label'],
                        'description' => self::AGE_GROUPS[$ageGroup]['description'],
                        'total' => 0,
                        'cancerTypes' => [],
                        'genderBreakdown' => ['male' => 0, 'female' => 0, 'other' => 0],
                        'stageBreakdown' => [],
                    ];
                }

                // Increment counts
                $ageGroupData[$ageGroup]['total']++;
                
                // Cancer type breakdown
                if (!isset($ageGroupData[$ageGroup]['cancerTypes'][$cancerType])) {
                    $ageGroupData[$ageGroup]['cancerTypes'][$cancerType] = 0;
                }
                $ageGroupData[$ageGroup]['cancerTypes'][$cancerType]++;

                // Gender breakdown
                $gender = strtolower($case->gender ?? 'other');
                if (!isset($ageGroupData[$ageGroup]['genderBreakdown'][$gender])) {
                    $ageGroupData[$ageGroup]['genderBreakdown'][$gender] = 0;
                }
                $ageGroupData[$ageGroup]['genderBreakdown'][$gender]++;

                // Stage breakdown
                if ($case->cancerStage) {
                    if (!isset($ageGroupData[$ageGroup]['stageBreakdown'][$case->cancerStage])) {
                        $ageGroupData[$ageGroup]['stageBreakdown'][$case->cancerStage] = 0;
                    }
                    $ageGroupData[$ageGroup]['stageBreakdown'][$case->cancerStage]++;
                }

                // Overall cancer type stats
                if (!isset($cancerTypeStats[$cancerType])) {
                    $cancerTypeStats[$cancerType] = 0;
                }
                $cancerTypeStats[$cancerType]++;
            }

            // Sort cancer types by count within each age group
            foreach ($ageGroupData as &$group) {
                arsort($group['cancerTypes']);
                arsort($group['stageBreakdown']);
            }

            // Sort age groups by order
            $orderedAgeGroups = [];
            foreach (self::AGE_GROUPS as $key => $value) {
                if (isset($ageGroupData[$key])) {
                    $orderedAgeGroups[$key] = $ageGroupData[$key];
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'ageGroups' => $orderedAgeGroups,
                    'cancerTypeStats' => $cancerTypeStats,
                    'totalCases' => $totalCases,
                    'summary' => [
                        'mostAffectedAgeGroup' => $this->getMostAffectedAgeGroup($orderedAgeGroups),
                        'topCancerTypes' => $this->getTopCancerTypes($cancerTypeStats, 5),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch cancer analytics by age',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get most affected age group
     */
    private function getMostAffectedAgeGroup(array $ageGroups): ?array
    {
        if (empty($ageGroups)) return null;

        $max = 0;
        $mostAffected = null;

        foreach ($ageGroups as $key => $group) {
            if ($group['total'] > $max) {
                $max = $group['total'];
                $mostAffected = [
                    'ageGroup' => $key,
                    'label' => $group['label'],
                    'total' => $group['total'],
                ];
            }
        }

        return $mostAffected;
    }

    /**
     * Get top N cancer types
     */
    private function getTopCancerTypes(array $cancerTypes, int $limit = 5): array
    {
        arsort($cancerTypes);
        return array_slice($cancerTypes, 0, $limit, true);
    }

    /**
     * Get detailed age distribution for a specific cancer type
     */
    public function getCancerTypeAgeDistribution(Request $request, string $cancerType): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $query = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.screeningResult', 'positive')
                ->where('case_outcomes.cancerType', $cancerType)
                ->whereNotNull('clients.dateOfBirth')
                ->select([
                    'clients.dateOfBirth',
                    'clients.gender',
                    'case_outcomes.cancerStage',
                    'case_outcomes.diagnosisDate',
                ]);

            // Apply RBAC
            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } else {
                if ($request->has('facilityId') && $request->facilityId !== 'all') {
                    $query->where('clients.facilityId', $request->facilityId);
                }
            }

            $cases = $query->get();

            $ageDistribution = [];
            foreach ($cases as $case) {
                $age = $this->calculateAge($case->dateOfBirth);
                $ageGroup = $this->getAgeClassification($age);
                
                if (!$ageGroup) continue;

                if (!isset($ageDistribution[$ageGroup])) {
                    $ageDistribution[$ageGroup] = [
                        'label' => self::AGE_GROUPS[$ageGroup]['label'],
                        'count' => 0,
                        'percentage' => 0,
                    ];
                }
                $ageDistribution[$ageGroup]['count']++;
            }

            // Calculate percentages
            $total = array_sum(array_column($ageDistribution, 'count'));
            foreach ($ageDistribution as &$group) {
                $group['percentage'] = $total > 0 ? round(($group['count'] / $total) * 100, 1) : 0;
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'cancerType' => $cancerType,
                    'distribution' => $ageDistribution,
                    'totalCases' => $total,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch age distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gender comparison across age groups
     */
    public function getGenderAgeComparison(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $query = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.screeningResult', 'positive')
                ->whereNotNull('clients.dateOfBirth')
                ->select(['clients.dateOfBirth', 'clients.gender']);

            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            }

            $cases = $query->get();

            $genderAgeData = [];
            foreach ($cases as $case) {
                $age = $this->calculateAge($case->dateOfBirth);
                $ageGroup = $this->getAgeClassification($age);
                $gender = strtolower($case->gender ?? 'other');
                
                if (!$ageGroup) continue;

                if (!isset($genderAgeData[$ageGroup])) {
                    $genderAgeData[$ageGroup] = [
                        'label' => self::AGE_GROUPS[$ageGroup]['label'],
                        'male' => 0,
                        'female' => 0,
                        'other' => 0,
                    ];
                }

                $genderAgeData[$ageGroup][$gender]++;
            }

            return response()->json([
                'status' => true,
                'data' => $genderAgeData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch gender comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stage 2 (Clinical Screening / PHC) overall outcome distribution —
     * normal / low_suspicion / suspicious / urgent_referral. Nothing
     * else aggregates this field; it's separate from the older
     * case_outcomes-based treatment/clinical outcome statistics.
     */
    public function getStage2OutcomeDistribution(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $query = DB::table('screening_visits')
                ->whereNotNull('overallOutcome');

            if (!$hasNationalAccess) {
                $query->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $query->where('facilityId', $request->facilityId);
            }

            if ($request->filled('dateFrom')) {
                $query->whereDate('visitDate', '>=', $request->dateFrom);
            }
            if ($request->filled('dateTo')) {
                $query->whereDate('visitDate', '<=', $request->dateTo);
            }

            $counts = $query
                ->select('overallOutcome', DB::raw('count(*) as total'))
                ->groupBy('overallOutcome')
                ->pluck('total', 'overallOutcome');

            $distribution = [
                'normal' => (int) ($counts['normal'] ?? 0),
                'low_suspicion' => (int) ($counts['low_suspicion'] ?? 0),
                'suspicious' => (int) ($counts['suspicious'] ?? 0),
                'urgent_referral' => (int) ($counts['urgent_referral'] ?? 0),
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'distribution' => $distribution,
                    'total' => array_sum($distribution),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch Stage 2 outcome distribution',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Facility-level performance comparison — screenings, referrals, and
     * flagged (suspicious/urgent) Stage 2 outcomes per facility.
     */
    public function getFacilityPerformance(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $screeningsQuery = DB::table('screening_visits')
                ->join('facilities', 'screening_visits.facilityId', '=', 'facilities.facilityId')
                ->select('facilities.facilityId', 'facilities.facilityName', DB::raw('count(*) as screeningsCount'));

            if (!$hasNationalAccess) {
                $screeningsQuery->whereIn('facilities.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->filled('facilityId') && $request->facilityId !== 'all') {
                $screeningsQuery->where('facilities.facilityId', $request->facilityId);
            }
            if ($request->filled('dateFrom')) {
                $screeningsQuery->whereDate('screening_visits.visitDate', '>=', $request->dateFrom);
            }
            if ($request->filled('dateTo')) {
                $screeningsQuery->whereDate('screening_visits.visitDate', '<=', $request->dateTo);
            }

            $screenings = $screeningsQuery
                ->groupBy('facilities.facilityId', 'facilities.facilityName')
                ->get()
                ->keyBy('facilityId');

            $referralsQuery = DB::table('client_referrals')
                ->join('facilities', 'client_referrals.fromFacilityId', '=', 'facilities.facilityId')
                ->select('facilities.facilityId', DB::raw('count(*) as referralsCount'));
            if (!$hasNationalAccess) {
                $referralsQuery->whereIn('facilities.facilityId', $user->visibleFacilityIds() ?? []);
            }
            $referrals = $referralsQuery->groupBy('facilities.facilityId')->get()->keyBy('facilityId');

            $flaggedQuery = DB::table('screening_visits')
                ->whereIn('overallOutcome', ['suspicious', 'urgent_referral'])
                ->select('facilityId', DB::raw('count(*) as flaggedCount'));
            if (!$hasNationalAccess) {
                $flaggedQuery->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
            }
            $flagged = $flaggedQuery->groupBy('facilityId')->get()->keyBy('facilityId');

            $result = $screenings->map(function ($row) use ($referrals, $flagged) {
                $facilityId = $row->facilityId;
                return [
                    'facilityId' => $facilityId,
                    'facilityName' => $row->facilityName,
                    'screeningsCount' => (int) $row->screeningsCount,
                    'referralsCount' => (int) ($referrals[$facilityId]->referralsCount ?? 0),
                    'flaggedCount' => (int) ($flagged[$facilityId]->flaggedCount ?? 0),
                ];
            })->sortByDesc('screeningsCount')->values();

            return response()->json(['status' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch facility performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Referral / patient journey funnel: Registered -> Screened -> Referred
     * -> Confirmed -> Treated, with conversion rate between each stage.
     */
    public function getReferralFunnel(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $scope = function ($query, string $column) use ($hasNationalAccess, $user, $request) {
                if (!$hasNationalAccess) {
                    $query->where($column, $user->facilityId);
                } elseif ($request->filled('facilityId') && $request->facilityId !== 'all') {
                    $query->where($column, $request->facilityId);
                }
                return $query;
            };

            $registeredQuery = DB::table('clients');
            $registered = $scope($registeredQuery, 'facilityId')->count();

            $screenedQuery = DB::table('screening_visits');
            $screened = $scope($screenedQuery, 'facilityId')->distinct()->count('clientId');

            $referredQuery = DB::table('client_referrals');
            $referred = $scope($referredQuery, 'fromFacilityId')->distinct()->count('clientId');

            $confirmedQuery = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.cancerConfirmed', 'yes');
            $confirmed = $scope($confirmedQuery, 'clients.facilityId')->count();

            $treatedQuery = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.treatmentCommenced', 'yes');
            $treated = $scope($treatedQuery, 'clients.facilityId')->count();

            $stages = [
                ['key' => 'registered', 'label' => 'Registered', 'count' => $registered],
                ['key' => 'screened', 'label' => 'Screened', 'count' => $screened],
                ['key' => 'referred', 'label' => 'Referred', 'count' => $referred],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'count' => $confirmed],
                ['key' => 'treated', 'label' => 'Treated', 'count' => $treated],
            ];

            foreach ($stages as $i => &$stage) {
                $stage['conversionFromPrevious'] = $i === 0
                    ? null
                    : ($stages[$i - 1]['count'] > 0 ? round(($stage['count'] / $stages[$i - 1]['count']) * 100, 1) : 0.0);
            }
            unset($stage);

            return response()->json(['status' => true, 'data' => $stages]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch referral funnel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Average days between key journey milestones. This is a starting
     * baseline (registration->screening, registration->referral,
     * diagnosis->treatment) — thresholds for what counts as "on time" vs
     * "delayed" should be reviewed against NICRAT's actual protocol targets.
     */
    public function getTimingMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $regToScreen = DB::table('clients')
                ->join('screening_visits', 'clients.clientId', '=', 'screening_visits.clientId')
                ->when(!$hasNationalAccess, fn ($q) => $q->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []))
                ->selectRaw('AVG(DATEDIFF(screening_visits.visitDate, clients.registrationDate)) as avgDays')
                ->first();

            $regToReferral = DB::table('client_referrals')
                ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
                ->when(!$hasNationalAccess, fn ($q) => $q->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []))
                ->selectRaw('AVG(DATEDIFF(client_referrals.referralDate, clients.registrationDate)) as avgDays')
                ->first();

            $diagnosisToTreatment = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->whereNotNull('diagnosisDate')
                ->whereNotNull('treatmentCommencementDate')
                ->when(!$hasNationalAccess, fn ($q) => $q->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []))
                ->selectRaw('AVG(DATEDIFF(treatmentCommencementDate, diagnosisDate)) as avgDays')
                ->first();

            return response()->json([
                'status' => true,
                'data' => [
                    'registrationToScreeningDays' => round((float) ($regToScreen->avgDays ?? 0), 1),
                    'registrationToReferralDays' => round((float) ($regToReferral->avgDays ?? 0), 1),
                    'diagnosisToTreatmentDays' => round((float) ($diagnosisToTreatment->avgDays ?? 0), 1),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch timing metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}