<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Support\ClientFindings;
use Illuminate\Support\Facades\Schema;

class CancerAnalyticsController extends Controller
{
    /**
     * Age classification ranges
     */
    private const AGE_GROUPS = [
        // Ranges must not overlap: before, 10-11 year olds fell into "Middle & Late Childhood" and
        // 15-19 into "Adolescents" only by list order, and 1-year-olds were counted as infants.
        'infancy' => ['label' => 'Infancy', 'min' => 0, 'max' => 0, 'description' => '0-12 months'],
        'early_childhood' => ['label' => 'Early Childhood', 'min' => 1, 'max' => 5, 'description' => '1-5 years'],
        'middle_late_childhood' => ['label' => 'Middle & Late Childhood', 'min' => 6, 'max' => 9, 'description' => '6-9 years'],
        'adolescents' => ['label' => 'Adolescents', 'min' => 10, 'max' => 19, 'description' => '10-19 years'],
        'youth' => ['label' => 'Youth', 'min' => 20, 'max' => 24, 'description' => '20-24 years'],
        'young_adults' => ['label' => 'Young Adults', 'min' => 25, 'max' => 44, 'description' => '25-44 years'],
        'middle_age' => ['label' => 'Middle Age', 'min' => 45, 'max' => 59, 'description' => '45-59 years'],
        'elderly' => ['label' => 'Elderly', 'min' => 60, 'max' => 74, 'description' => '60-74 years'],
        'senior' => ['label' => 'Senior', 'min' => 75, 'max' => 150, 'description' => '75+ years'],
    ];

    /**
     * Facility limit for this request: null = every facility. Facility and date filters apply to national
     * users; everyone else is limited to the facilities they can see.
     * Returns [facilities|null, dateFrom|null, dateTo|null].
     */
    private function scope(Request $request): array
    {
        $user = Auth::user();
        if (!$this->hasNationalAccess($user)) {
            return [$user->visibleFacilityIds() ?? [], null, null];
        }
        $facilities = $request->filled('facilityId') && $request->facilityId !== 'all' ? [$request->facilityId] : null;
        return [$facilities, $request->filled('dateFrom') ? $request->dateFrom : null, $request->filled('dateTo') ? $request->dateTo : null];
    }

    private static function typeLabel(?string $t): string
    {
        $t = trim((string) $t);
        return $t === '' ? 'Not specified' : ucwords(str_replace('_', ' ', strtolower($t)));
    }

    /**
     * Cases to analyse, one row per client:
     *   basis=suspicious (default) — clients with a suspicious finding (ClientFindings: screening forms, visit
     *       outcome and lab results, the same rule as Screened Clients). Age is taken at the first screening.
     *   basis=confirmed — clients whose case outcome says cancer is confirmed. Age at diagnosis.
     * Row: clientId, cancerTypes[], gender, age|null, stage|null
     */
    private function caseRows(string $basis, ?array $facilities, ?string $from, ?string $to): array
    {
        $rows = [];
        if ($basis === 'confirmed') {
            $list = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->where('case_outcomes.cancerConfirmed', 'yes')
                ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate('case_outcomes.diagnosisDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('case_outcomes.diagnosisDate', '<=', $to))
                ->select('clients.clientId', 'clients.dateOfBirth', 'clients.gender', 'case_outcomes.cancerType',
                         'case_outcomes.cancerStage', 'case_outcomes.diagnosisDate')
                ->get();
            foreach ($list as $c) {
                $rows[$c->clientId] = [
                    'clientId' => $c->clientId,
                    'cancerTypes' => [self::typeLabel($c->cancerType)],
                    'gender' => $c->gender,
                    'age' => $this->ageAt($c->dateOfBirth, $c->diagnosisDate),
                    'stage' => $c->cancerStage,
                ];
            }
            return array_values($rows);
        }

        $findings = ClientFindings::build($facilities, $from, $to);
        $suspicious = array_filter($findings, fn ($c) => ClientFindings::isSuspicious($c));
        if (!$suspicious) return [];

        $ids = array_keys($suspicious);
        $clients = collect();
        $stages = collect();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $clients = $clients->merge(DB::table('clients')->whereIn('clientId', $chunk)->get(['clientId', 'dateOfBirth', 'gender'])->keyBy('clientId'));
            if (Schema::hasTable('case_outcomes')) {
                $stages = $stages->merge(DB::table('case_outcomes')->whereIn('clientId', $chunk)->whereNotNull('cancerStage')->pluck('cancerStage', 'clientId'));
            }
        }
        foreach ($suspicious as $id => $f) {
            $types = array_keys(array_filter($f['types'], fn ($v) => $v === 'suspicious'));
            $c = $clients[$id] ?? null;
            $rows[] = [
                'clientId' => $id,
                'cancerTypes' => $types ? array_map([self::class, 'typeLabel'], $types) : ['Not specified'],
                'gender' => $c->gender ?? null,
                'age' => $c ? $this->ageAt($c->dateOfBirth, $f['firstDate']) : null,
                'stage' => $stages[$id] ?? null,
            ];
        }
        return $rows;
    }

    /** Whole years between date of birth and the given date (today if none). */
    private function ageAt($dateOfBirth, $onDate = null): ?int
    {
        if (!$dateOfBirth) return null;
        try {
            $at = $onDate ? Carbon::parse($onDate) : now();
            $dob = Carbon::parse($dateOfBirth);
            return $dob->gt($at) ? null : (int) $dob->diffInYears($at);
        } catch (\Throwable $e) {
            return null;
        }
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
     * Cancer types by age classification.
     * ?basis=suspicious (default) | confirmed  &facilityId= &dateFrom= &dateTo=
     * Each client is counted once per age group; a client suspicious for two cancer types counts under both types.
     */
    public function getCancerByAgeClassification(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->scope($request);
            $basis = $request->query('basis') === 'confirmed' ? 'confirmed' : 'suspicious';
            $cases = $this->caseRows($basis, $facilities, $from, $to);

            $ageGroupData = [];
            $cancerTypeStats = [];
            $totalCases = 0;
            $unknownAge = 0;

            foreach ($cases as $case) {
                $ageGroup = $case['age'] === null ? null : $this->getAgeClassification($case['age']);
                foreach ($case['cancerTypes'] as $t) {
                    $cancerTypeStats[$t] = ($cancerTypeStats[$t] ?? 0) + 1;
                }
                $totalCases++;
                if (!$ageGroup) {
                    $unknownAge++;
                    continue;
                }

                $g = &$ageGroupData[$ageGroup];
                $g ??= [
                    'label' => self::AGE_GROUPS[$ageGroup]['label'],
                    'description' => self::AGE_GROUPS[$ageGroup]['description'],
                    'total' => 0,
                    'cancerTypes' => [],
                    'genderBreakdown' => ['male' => 0, 'female' => 0, 'other' => 0],
                    'stageBreakdown' => [],
                ];
                $g['total']++;
                foreach ($case['cancerTypes'] as $t) {
                    $g['cancerTypes'][$t] = ($g['cancerTypes'][$t] ?? 0) + 1;
                }
                $gender = strtolower((string) $case['gender']);
                $gender = in_array($gender, ['male', 'female'], true) ? $gender : 'other';
                $g['genderBreakdown'][$gender]++;
                if ($case['stage']) {
                    $stage = str_replace(['stage_', 'Stage '], '', (string) $case['stage']);
                    $g['stageBreakdown'][$stage] = ($g['stageBreakdown'][$stage] ?? 0) + 1;
                }
                unset($g);
            }

            foreach ($ageGroupData as &$group) {
                arsort($group['cancerTypes']);
                ksort($group['stageBreakdown']);
            }
            unset($group);

            $orderedAgeGroups = [];
            foreach (self::AGE_GROUPS as $key => $value) {
                if (isset($ageGroupData[$key])) {
                    $orderedAgeGroups[$key] = $ageGroupData[$key];
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'basis' => $basis,
                    'ageGroups' => $orderedAgeGroups,
                    'cancerTypeStats' => $cancerTypeStats,
                    'totalCases' => $totalCases,
                    'unknownAge' => $unknownAge,
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
     * Age distribution for one cancer type (?basis=suspicious|confirmed, same cases as cancer-by-age).
     */
    public function getCancerTypeAgeDistribution(Request $request, string $cancerType): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->scope($request);
            $basis = $request->query('basis') === 'confirmed' ? 'confirmed' : 'suspicious';
            $label = self::typeLabel($cancerType);

            $ageDistribution = [];
            foreach ($this->caseRows($basis, $facilities, $from, $to) as $case) {
                if (!in_array($label, $case['cancerTypes'], true)) continue;
                $ageGroup = $case['age'] === null ? null : $this->getAgeClassification($case['age']);
                if (!$ageGroup) continue;
                $ageDistribution[$ageGroup] ??= ['label' => self::AGE_GROUPS[$ageGroup]['label'], 'count' => 0, 'percentage' => 0];
                $ageDistribution[$ageGroup]['count']++;
            }

            $total = array_sum(array_column($ageDistribution, 'count'));
            foreach ($ageDistribution as &$group) {
                $group['percentage'] = $total > 0 ? round(($group['count'] / $total) * 100, 1) : 0;
            }
            unset($group);

            return response()->json([
                'status' => true,
                'data' => ['cancerType' => $cancerType, 'basis' => $basis, 'distribution' => $ageDistribution, 'totalCases' => $total],
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
     * Gender comparison across age groups — same cases, basis and filters as cancer-by-age.
     */
    public function getGenderAgeComparison(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->scope($request);
            $basis = $request->query('basis') === 'confirmed' ? 'confirmed' : 'suspicious';

            $genderAgeData = [];
            foreach ($this->caseRows($basis, $facilities, $from, $to) as $case) {
                $ageGroup = $case['age'] === null ? null : $this->getAgeClassification($case['age']);
                if (!$ageGroup) continue;
                $genderAgeData[$ageGroup] ??= ['label' => self::AGE_GROUPS[$ageGroup]['label'], 'male' => 0, 'female' => 0, 'other' => 0];
                $gender = strtolower((string) $case['gender']);
                $genderAgeData[$ageGroup][in_array($gender, ['male', 'female'], true) ? $gender : 'other']++;
            }

            $ordered = [];
            foreach (array_keys(self::AGE_GROUPS) as $key) {
                if (isset($genderAgeData[$key])) $ordered[$key] = $genderAgeData[$key];
            }

            return response()->json(['status' => true, 'data' => $ordered]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch gender comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stage 2 results. The old version counted only visits with a saved overall outcome, which missed
     * suspicious results recorded on the screening forms and in the lab. Now the same client-level
     * summary as /analytics/stage2-summary.
     */
    public function getStage2OutcomeDistribution(Request $request): JsonResponse
    {
        return app(DashboardController::class)->getStage2Summary($request);
    }

    /**
     * Stage 4 — treatment outcome distribution and modality usage
     * across the user's visible facilities.
     */
    /**
     * Socio-economic status breakdown (NICRAT model) across the client
     * population — used to help identify clients who may need
     * financial/support assistance (indigency), and to see whether
     * lower socio-economic class correlates with higher cancer risk or
     * confirmed diagnoses in this population.
     */
    public function getSocioeconomicAnalytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $query = DB::table('client_risk_profiles')
                ->join('clients', 'client_risk_profiles.clientId', '=', 'clients.clientId')
                ->whereNotNull('client_risk_profiles.socioeconomicClass');

            if (!$hasNationalAccess) {
                $query->whereIn('clients.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $query->where('clients.facilityId', $request->facilityId);
            }

            $classDistribution = (clone $query)
                ->select('client_risk_profiles.socioeconomicClass', DB::raw('count(*) as total'))
                ->groupBy('client_risk_profiles.socioeconomicClass')
                ->pluck('total', 'socioeconomicClass');

            // Cross-tab: does lower socio-economic class correlate with
            // higher cancer risk in this population?
            $classByCancerRisk = (clone $query)
                ->whereNotNull('client_risk_profiles.cancerRiskCategory')
                ->select(
                    'client_risk_profiles.socioeconomicClass',
                    'client_risk_profiles.cancerRiskCategory',
                    DB::raw('count(*) as total'),
                )
                ->groupBy('client_risk_profiles.socioeconomicClass', 'client_risk_profiles.cancerRiskCategory')
                ->get()
                ->groupBy('socioeconomicClass')
                ->map(fn ($rows) => $rows->pluck('total', 'cancerRiskCategory'));

            // Of lower-class clients, how many have a confirmed cancer
            // diagnosis and/or an active treatment plan — the group most
            // likely to need support right now.
            $lowerClassClientIds = (clone $query)
                ->where('client_risk_profiles.socioeconomicClass', 'lower')
                ->pluck('clients.clientId');

            $lowerClassWithConfirmedCancer = DB::table('case_outcomes')
                ->whereIn('clientId', $lowerClassClientIds)
                ->where('cancerConfirmed', 'yes')
                ->count();

            $lowerClassWithActiveTreatment = DB::table('treatment_plans')
                ->whereIn('clientId', $lowerClassClientIds)
                ->where('status', 'active')
                ->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'classDistribution' => $classDistribution,
                    'classByCancerRisk' => $classByCancerRisk,
                    'lowerClassTotal' => $lowerClassClientIds->count(),
                    'lowerClassWithConfirmedCancer' => $lowerClassWithConfirmedCancer,
                    'lowerClassWithActiveTreatment' => $lowerClassWithActiveTreatment,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch socioeconomic analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTreatmentAnalytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasNationalAccess = $this->hasNationalAccess($user);

            $plansQuery = DB::table('treatment_plans');
            if (!$hasNationalAccess) {
                $plansQuery->whereIn('facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $plansQuery->where('facilityId', $request->facilityId);
            }

            $outcomeCounts = (clone $plansQuery)
                ->whereNotNull('treatmentOutcome')
                ->select('treatmentOutcome', DB::raw('count(*) as total'))
                ->groupBy('treatmentOutcome')
                ->pluck('total', 'treatmentOutcome');

            $intentCounts = (clone $plansQuery)
                ->whereNotNull('treatmentIntent')
                ->select('treatmentIntent', DB::raw('count(*) as total'))
                ->groupBy('treatmentIntent')
                ->pluck('total', 'treatmentIntent');

            $modalityQuery = DB::table('treatment_records')
                ->join('treatment_plans', 'treatment_records.treatmentPlanId', '=', 'treatment_plans.treatmentPlanId');
            if (!$hasNationalAccess) {
                $modalityQuery->whereIn('treatment_plans.facilityId', $user->visibleFacilityIds() ?? []);
            } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
                $modalityQuery->where('treatment_plans.facilityId', $request->facilityId);
            }

            $modalityCounts = $modalityQuery
                ->select('treatment_records.modalityType', DB::raw('count(*) as total'))
                ->groupBy('treatment_records.modalityType')
                ->pluck('total', 'modalityType');

            return response()->json([
                'status' => true,
                'data' => [
                    'activePlans' => (clone $plansQuery)->where('status', 'active')->count(),
                    'closedPlans' => (clone $plansQuery)->where('status', 'closed')->count(),
                    'outcomeDistribution' => $outcomeCounts,
                    'intentDistribution' => $intentCounts,
                    'modalityUsage' => $modalityCounts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch treatment analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Facility performance — now the same as /analytics/facility-summary (clients screened, suspicious,
     * referred, awaiting/overdue lab), with the old field names kept for older screens.
     */
    public function getFacilityPerformance(Request $request): JsonResponse
    {
        $res = app(DashboardController::class)->getFacilitySummary($request);
        $payload = $res->getData(true);
        if (!empty($payload['status'])) {
            $payload['data'] = array_map(fn ($r) => $r + [
                'screeningsCount' => $r['screened'],
                'referralsCount' => $r['referred'],
                'flaggedCount' => $r['suspicious'],
            ], $payload['data']);
        }
        return response()->json($payload, $res->getStatusCode());
    }

    /**
     * Patient journey funnel — now the same cohort funnel as /analytics/journey-funnel.
     */
    public function getReferralFunnel(Request $request): JsonResponse
    {
        return app(DashboardController::class)->getJourneyFunnel($request);
    }

    /**
     * Average and median days between journey milestones, per client:
     *   registration -> first screening (form or lab sample)
     *   registration -> first referral (referral record)
     *   first sample -> released result (lab turnaround, per sample)
     *   diagnosis -> treatment start (case outcomes)
     * Negative gaps (dates entered out of order) are left out and counted in `skipped`.
     * Before: every visit (follow-ups included) was averaged, negatives were kept and no filters applied.
     */
    public function getTimingMetrics(Request $request): JsonResponse
    {
        try {
            [$facilities, $from, $to] = $this->scope($request);

            $stat = function (array $days) {
                $valid = array_values(array_filter($days, fn ($d) => $d !== null && $d >= 0));
                sort($valid);
                $n = count($valid);
                return [
                    'avg' => $n ? round(array_sum($valid) / $n, 1) : null,
                    'median' => $n ? ($n % 2 ? $valid[intdiv($n, 2)] : round(($valid[$n / 2 - 1] + $valid[$n / 2]) / 2, 1)) : null,
                    'count' => $n,
                    'skipped' => count(array_filter($days, fn ($d) => $d !== null && $d < 0)),
                ];
            };
            $diff = fn ($a, $b) => ($a && $b) ? (int) floor((strtotime(substr($b, 0, 10)) - strtotime(substr($a, 0, 10))) / 86400) : null;

            // Registration -> first screening
            $findings = ClientFindings::build($facilities, $from, $to);
            $screenedIds = ClientFindings::screenedIds($findings);
            $regDates = collect();
            foreach (array_chunk($screenedIds, 1000) as $chunk) {
                $regDates = $regDates->merge(DB::table('clients')->whereIn('clientId', $chunk)->pluck('registrationDate', 'clientId'));
            }
            $regToScreen = [];
            foreach ($screenedIds as $id) {
                $regToScreen[] = $diff($regDates[$id] ?? null, $findings[$id]['firstDate']);
            }

            // Registration -> first referral record
            $firstRef = DB::table('client_referrals')
                ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
                ->when($facilities !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('client_referrals.fromFacilityId', $facilities)
                    ->orWhere(fn ($x) => $x->whereNull('client_referrals.fromFacilityId')->whereIn('clients.facilityId', $facilities))))
                ->when($from, fn ($q) => $q->whereDate('client_referrals.referralDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('client_referrals.referralDate', '<=', $to))
                ->groupBy('clients.clientId', 'clients.registrationDate')
                ->selectRaw('clients.registrationDate as reg, MIN(client_referrals.referralDate) as ref')
                ->get();
            $regToRef = $firstRef->map(fn ($r) => $diff($r->reg, $r->ref))->all();

            // Sample -> released result
            $hasDeletedAt = Schema::hasColumn('screening_samples', 'deletedAt');
            $turn = DB::table('screening_samples')
                ->when($hasDeletedAt, fn ($q) => $q->whereNull('deletedAt'))
                ->where('status', 'result_released')
                ->whereNotNull('resultDate')
                ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate('sampleDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('sampleDate', '<=', $to))
                ->get(['sampleDate', 'resultDate'])
                ->map(fn ($r) => $diff($r->sampleDate, $r->resultDate))->all();

            // Diagnosis -> treatment start
            $dx = DB::table('case_outcomes')
                ->join('clients', 'case_outcomes.clientId', '=', 'clients.clientId')
                ->whereNotNull('case_outcomes.diagnosisDate')
                ->whereNotNull('case_outcomes.treatmentCommencementDate')
                ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate('case_outcomes.diagnosisDate', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('case_outcomes.diagnosisDate', '<=', $to))
                ->get(['case_outcomes.diagnosisDate', 'case_outcomes.treatmentCommencementDate'])
                ->map(fn ($r) => $diff($r->diagnosisDate, $r->treatmentCommencementDate))->all();

            $s1 = $stat($regToScreen);
            $s2 = $stat($regToRef);
            $s3 = $stat($turn);
            $s4 = $stat($dx);

            return response()->json([
                'status' => true,
                'data' => [
                    // old field names (averages) kept for older screens
                    'registrationToScreeningDays' => $s1['avg'] ?? 0,
                    'registrationToReferralDays' => $s2['avg'] ?? 0,
                    'diagnosisToTreatmentDays' => $s4['avg'] ?? 0,
                    'metrics' => [
                        ['key' => 'registration_to_screening', 'label' => 'Registration → first screening'] + $s1,
                        ['key' => 'registration_to_referral', 'label' => 'Registration → first referral'] + $s2,
                        ['key' => 'sample_to_result', 'label' => 'Sample taken → result released'] + $s3,
                        ['key' => 'diagnosis_to_treatment', 'label' => 'Diagnosis → treatment start'] + $s4,
                    ],
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