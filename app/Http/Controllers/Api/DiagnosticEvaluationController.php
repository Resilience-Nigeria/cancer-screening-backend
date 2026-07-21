<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseOutcome;
use App\Models\Client;
use App\Models\ClientReferral;
use App\Models\DiagnosticEvaluation;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticEvaluationController extends Controller
{
    /**
     * Referrals routed to the current user's facility for Stage 3 that
     * haven't been started yet — this is how Hub (or whichever facility
     * is configured Stage 3-capable) staff see who's waiting on them.
     */
    public function pendingReferrals(Request $request): JsonResponse
    {
        $user = $request->user();
        $facility = $user->facility;

        if (!$facility || !$facility->supportsStage('stage3')) {
            return response()->json([
                'message' => 'Your facility is not configured for Stage 3 (Diagnostic Evaluation).',
            ], 403);
        }

        $visibleIds = $user->visibleFacilityIds();

        $referrals = ClientReferral::with(['client', 'fromFacility'])
            ->where('referralType', 'screening_to_confirmation')
            ->whereIn('status', ['pending', 'accepted'])
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('toFacilityId', $visibleIds))
            ->when($visibleIds === null, fn ($q) => $q->where('toFacilityId', $facility->facilityId))
            ->whereDoesntHave('client.diagnosticEvaluations', function ($q) {
                $q->where('status', 'completed');
            })
            ->latest('referralDate')
            ->get();

        return response()->json(['referrals' => $referrals]);
    }

    /**
     * Everything Section A (Specialist Consultation) needs to review —
     * pulled from existing records, not re-entered. Also usable for a
     * client with no referral (a facility that's both Stage 2 and
     * Stage 3-capable can start an evaluation directly).
     */
    public function clientContext(Request $request, string $clientId): JsonResponse
    {
        $client = Client::with(['latestRiskProfile', 'visits' => function ($q) {
            $q->with(['breastScreening', 'cervicalScreening', 'prostateScreening', 'colorectalScreening', 'liverScreening'])
              ->orderByDesc('visitDate');
        }])->where('clientId', $clientId)->firstOrFail();

        if (!$request->user()->canAccessFacility($client->facilityId)) {
            return response()->json(['message' => 'Not authorized to view this client.'], 403);
        }

        return response()->json([
            'client' => $client,
            'riskProfile' => $client->latestRiskProfile,
            'visits' => $client->visits,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $facility = $user->facility;

        if (!$facility || !$facility->supportsStage('stage3')) {
            return response()->json([
                'message' => 'Your facility is not configured for Stage 3 (Diagnostic Evaluation).',
            ], 422);
        }

        $validated = $request->validate([
            'clientId' => 'required|string|exists:clients,clientId',
            'referralId' => 'nullable|integer|exists:client_referrals,referralId',
            'suspectedCancerType' => 'required|in:breast,cervical,prostate,colorectal,lung,liver,oral',
            'evaluationDate' => 'required|date',
        ]);

        $client = Client::where('clientId', $validated['clientId'])->firstOrFail();

        $evaluation = DiagnosticEvaluation::create([
            ...$validated,
            'facilityId' => $facility->facilityId,
            'status' => 'in_progress',
        ]);

        if (!empty($validated['referralId'])) {
            ClientReferral::where('referralId', $validated['referralId'])
                ->update(['status' => 'accepted']);
        }

        return response()->json([
            'message' => 'Diagnostic evaluation started.',
            'evaluation' => $evaluation,
        ], 201);
    }

    public function show(Request $request, DiagnosticEvaluation $evaluation): JsonResponse
    {
        if (!$request->user()->canAccessFacility($evaluation->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        return response()->json(['evaluation' => $evaluation->load(['client', 'facility', 'referral'])]);
    }

    /**
     * Sections A-C — consultation, advanced exam, diagnostic tests,
     * blood investigations. Separate from finalizePathology() since
     * pathology (Section D) is the definitive step that closes out the
     * referral and updates the client's journey stage.
     */
    public function update(Request $request, DiagnosticEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();

        if (!$user->canAccessFacility($evaluation->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'consultationNotes' => 'nullable|string',
            'advancedExaminationFindings' => 'nullable|string',
            'diagnosticTests' => 'nullable|array',
            'bloodInvestigations' => 'nullable|array',
        ]);

        if ($request->filled('consultationNotes') && !$evaluation->consultedAt) {
            $validated['consultedBy'] = $user->id;
            $validated['consultedAt'] = now();
        }

        $evaluation->update($validated);

        return response()->json([
            'message' => 'Evaluation updated.',
            'evaluation' => $evaluation,
        ]);
    }

    /**
     * Section D — Pathology. The definitive diagnosis. Finalizing this
     * marks the referral completed, updates the client's journey stage,
     * and syncs into case_outcomes so existing analytics (cancer-by-age,
     * the referral funnel, etc.) reflect real Stage 3 data.
     */
    public function finalizePathology(Request $request, DiagnosticEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();

        if (!$user->canAccessFacility($evaluation->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'histopathologyResult' => 'required|in:benign,pre_cancer,malignant,inconclusive',
            'pathologyNotes' => 'nullable|string',
            'pathologyDate' => 'required|date',
        ]);

        $evaluation->update([
            ...$validated,
            'status' => 'completed',
            'completedBy' => $user->id,
            'completedAt' => now(),
        ]);

        $client = $evaluation->client;

        if ($evaluation->referralId) {
            ClientReferral::where('referralId', $evaluation->referralId)
                ->update(['status' => 'completed']);
        }

        if ($client) {
            $client->update([
                'journeyStage' => $evaluation->histopathologyResult === 'malignant' ? 'treatment' : 'followup',
            ]);

            // Sync into case_outcomes so analytics built on that table
            // (cancer-by-age, the referral funnel, etc.) reflect real
            // Stage 3 results rather than staying empty.
            CaseOutcome::updateOrCreate(
                ['clientId' => $client->clientId],
                [
                    'cancerConfirmed' => $evaluation->histopathologyResult === 'malignant' ? 'yes' : 'no',
                    'cancerType' => $evaluation->suspectedCancerType,
                    'diagnosisDate' => $evaluation->pathologyDate,
                    'diagnosis' => $evaluation->histopathologyResult,
                    'screeningResult' => $evaluation->histopathologyResult === 'malignant' ? 'positive' : 'negative',
                ],
            );
        }

        return response()->json([
            'message' => 'Pathology result recorded — evaluation complete.',
            'evaluation' => $evaluation,
        ]);
    }
}
