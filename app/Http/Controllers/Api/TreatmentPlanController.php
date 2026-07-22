<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseOutcome;
use App\Models\Client;
use App\Models\DiagnosticEvaluation;
use App\Models\FollowUpProtocolTemplate;
use App\Models\FollowUpSchedule;
use App\Models\TreatmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentPlanController extends Controller
{
    /**
     * Completed Stage 3 evaluations (a histopathology result exists)
     * that don't yet have a Stage 4 treatment plan — the inbox for
     * whichever facility is configured to handle stage4.
     */
    public function pendingEvaluations(Request $request): JsonResponse
    {
        $user = $request->user();
        $facility = $user->facility;

        if (!$facility || !$facility->supportsStage('stage4')) {
            return response()->json([
                'message' => 'Your facility is not configured for Stage 4 (Treatment & Care Management).',
            ], 403);
        }

        $visibleIds = $user->visibleFacilityIds();

        $evaluations = DiagnosticEvaluation::with('client')
            ->where('status', 'completed')
            ->where('decisionPathway', 'cancer_confirmed')
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('facilityId', $visibleIds))
            ->when($visibleIds === null, fn ($q) => $q->where('facilityId', $facility->facilityId))
            ->whereDoesntHave('client.treatmentPlans')
            ->latest('pathologyDate')
            ->get();

        return response()->json(['evaluations' => $evaluations]);
    }

    /**
     * Everything 4.1 (Review of Diagnostic Findings) needs — pulled
     * forward from Stage 3, not re-entered: histopathology, diagnostic
     * tests, blood investigations, plus risk profile and prior Stage 2
     * findings that Stage 3 itself already surfaced.
     */
    public function clientContext(Request $request, string $clientId): JsonResponse
    {
        $client = Client::with(['latestRiskProfile'])
            ->where('clientId', $clientId)
            ->orWhere('phoneNumber', $clientId)
            ->first();

        if (!$client) {
            return response()->json(['message' => 'No client found with that ID or phone number.'], 404);
        }

        if (!$request->user()->canAccessFacility($client->facilityId)) {
            return response()->json(['message' => 'Not authorized to view this client.'], 403);
        }

        $latestEvaluation = DiagnosticEvaluation::where('clientId', $client->clientId)
            ->where('status', 'completed')
            ->latest('pathologyDate')
            ->first();

        return response()->json([
            'client' => $client,
            'riskProfile' => $client->latestRiskProfile,
            'evaluation' => $latestEvaluation,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $facility = $user->facility;

        if (!$facility || !$facility->supportsStage('stage4')) {
            return response()->json([
                'message' => 'Your facility is not configured for Stage 4 (Treatment & Care Management).',
            ], 422);
        }

        $validated = $request->validate([
            'clientId' => 'required|string|exists:clients,clientId',
            'evaluationId' => 'nullable|integer|exists:diagnostic_evaluations,evaluationId',
        ]);

        $plan = TreatmentPlan::create([
            ...$validated,
            'facilityId' => $facility->facilityId,
            'createdBy' => $user->id,
            'status' => 'active',
        ]);

        return response()->json(['message' => 'Treatment plan started.', 'plan' => $plan], 201);
    }

    public function show(Request $request, TreatmentPlan $plan): JsonResponse
    {
        if (!$request->user()->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        return response()->json([
            'plan' => $plan->load(['client', 'evaluation', 'treatmentRecords', 'monitoringLogs', 'followUpSchedules']),
        ]);
    }

    /**
     * 4.1 Review + 4.3 Staging + 4.4 MDT + 4.5 Treatment Planning.
     * Grouped into one update since they're all part of pre-treatment
     * workup, before any modality is recorded.
     */
    public function update(Request $request, TreatmentPlan $plan): JsonResponse
    {
        if (!$request->user()->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            // 4.1
            'performanceStatusScale' => 'nullable|in:ecog,karnofsky',
            'performanceStatusValue' => 'nullable|string',
            'comorbidities' => 'nullable|string',
            'patientPreferencesNotes' => 'nullable|string',
            'consentObtained' => 'nullable|boolean',
            'consentDate' => 'nullable|date',
            // 4.3
            'tStage' => 'nullable|string',
            'nStage' => 'nullable|string',
            'mStage' => 'nullable|string',
            'clinicalStage' => 'nullable|in:I,II,III,IV',
            'histologicalType' => 'nullable|string',
            'tumourGrade' => 'nullable|string',
            'biomarkers' => 'nullable|array',
            // 4.4
            'mdtParticipants' => 'nullable|array',
            'mdtDate' => 'nullable|date',
            'mdtDecisionNotes' => 'nullable|string',
            'clinicalTrialEligible' => 'nullable|boolean',
            // 4.5
            'treatmentIntent' => 'nullable|in:curative,neoadjuvant,adjuvant,disease_control,palliative',
        ]);

        $plan->update($validated);

        return response()->json(['message' => 'Treatment plan updated.', 'plan' => $plan]);
    }

    /**
     * 4.8 Treatment Outcome + 4.9 Survivorship Care Plan. Finalizing
     * this generates the 4.10 follow-up schedule from whichever
     * protocol template applies, and closes the plan.
     */
    public function finalizeOutcome(Request $request, TreatmentPlan $plan): JsonResponse
    {
        if (!$request->user()->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'treatmentOutcome' => 'required|in:complete_response,partial_response,stable_disease,progressive_disease,recurrence,remission,disease_free,deceased,lost_to_followup',
            'outcomeDate' => 'required|date',
            'outcomeNotes' => 'nullable|string',
            'survivorshipPlan' => 'nullable|array',
        ]);

        $plan->update([...$validated, 'status' => 'closed']);

        if ($plan->client && !in_array($validated['treatmentOutcome'], ['deceased'], true)) {
            $plan->client->update(['journeyStage' => 'followup']);
        }

        $this->generateFollowUpSchedule($plan);

        return response()->json([
            'message' => 'Treatment outcome recorded — follow-up schedule generated.',
            'plan' => $plan->load('followUpSchedules'),
        ]);
    }

    /**
     * 4.10 — builds the actual appointment dates from whichever
     * protocol template applies (cancer-type-specific override if one
     * exists, otherwise the "all" default), rather than hardcoding
     * 1/3/6/12 months in code.
     */
    protected function generateFollowUpSchedule(TreatmentPlan $plan): void
    {
        $cancerType = $plan->evaluation?->suspectedCancerType;

        $templates = FollowUpProtocolTemplate::where('isActive', true)
            ->where(function ($q) use ($cancerType) {
                $q->where('cancerType', 'all');
                if ($cancerType) {
                    $q->orWhere('cancerType', $cancerType);
                }
            })
            ->get()
            ->groupBy('monthsAfterTreatment')
            ->map(fn ($group) => $group->firstWhere('cancerType', $cancerType) ?? $group->first());

        $baseDate = now();

        foreach ($templates as $template) {
            FollowUpSchedule::create([
                'treatmentPlanId' => $plan->treatmentPlanId,
                'dueDate' => $baseDate->copy()->addMonths($template->monthsAfterTreatment)->toDateString(),
                'activities' => $template->activities,
                'status' => 'pending',
            ]);

            // Recurring annual follow-ups — generate the next 4 years'
            // worth so there's always something on the calendar without
            // needing a separate yearly generation job.
            if ($template->isRecurringAnnually) {
                for ($year = 2; $year <= 5; $year++) {
                    FollowUpSchedule::create([
                        'treatmentPlanId' => $plan->treatmentPlanId,
                        'dueDate' => $baseDate->copy()->addMonths($template->monthsAfterTreatment)->addYears($year - 1)->toDateString(),
                        'activities' => $template->activities,
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }
}
