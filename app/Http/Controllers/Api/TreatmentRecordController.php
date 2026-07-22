<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentPlan;
use App\Models\TreatmentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentRecordController extends Controller
{
    public function index(Request $request, TreatmentPlan $plan): JsonResponse
    {
        if (!$request->user()->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        return response()->json(['records' => $plan->treatmentRecords]);
    }

    /**
     * modalityDetails holds whatever fields are relevant to the
     * specific modality (validated loosely as an array here — each
     * frontend screen is responsible for sending the right shape for
     * its own modality, since the 7 types have entirely different
     * fields: surgical margins for Surgery, regimen/cycle for
     * Chemotherapy, biomarker/response for Targeted Therapy, etc.)
     */
    public function store(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $user = $request->user();

        if (!$user->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'modalityType' => 'required|in:surgery,chemotherapy,radiotherapy,hormonal_therapy,immunotherapy,targeted_therapy,palliative_care',
            'startDate' => 'nullable|date',
            'completionDate' => 'nullable|date',
            'completionStatus' => 'nullable|in:ongoing,completed,discontinued',
            'reasonForDiscontinuation' => 'nullable|string',
            'notes' => 'nullable|string',
            'modalityDetails' => 'nullable|array',
        ]);

        $record = TreatmentRecord::create([
            ...$validated,
            'treatmentPlanId' => $plan->treatmentPlanId,
            'recordedBy' => $user->id,
        ]);

        return response()->json(['message' => 'Treatment record added.', 'record' => $record], 201);
    }

    public function update(Request $request, TreatmentRecord $record): JsonResponse
    {
        if (!$request->user()->canAccessFacility($record->treatmentPlan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'startDate' => 'nullable|date',
            'completionDate' => 'nullable|date',
            'completionStatus' => 'nullable|in:ongoing,completed,discontinued',
            'reasonForDiscontinuation' => 'nullable|string',
            'notes' => 'nullable|string',
            'modalityDetails' => 'nullable|array',
        ]);

        $record->update($validated);

        return response()->json(['message' => 'Treatment record updated.', 'record' => $record]);
    }

    public function destroy(Request $request, TreatmentRecord $record): JsonResponse
    {
        if (!$request->user()->canAccessFacility($record->treatmentPlan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $record->delete();

        return response()->json(['message' => 'Treatment record removed.']);
    }
}
