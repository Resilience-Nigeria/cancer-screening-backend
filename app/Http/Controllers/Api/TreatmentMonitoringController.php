<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentMonitoringLog;
use App\Models\TreatmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentMonitoringController extends Controller
{
    public function index(Request $request, TreatmentPlan $plan): JsonResponse
    {
        if (!$request->user()->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        return response()->json(['logs' => $plan->monitoringLogs()->orderByDesc('logDate')->get()]);
    }

    public function store(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $user = $request->user();

        if (!$user->canAccessFacility($plan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'logDate' => 'required|date',
            'attended' => 'nullable|boolean',
            'missedAppointment' => 'nullable|boolean',
            'toxicity' => 'nullable|string',
            'labResults' => 'nullable|array',
            'imagingResponse' => 'nullable|string',
            'clinicalResponse' => 'nullable|string',
            'doseModification' => 'nullable|string',
            'treatmentInterruption' => 'nullable|boolean',
            'hospitalAdmission' => 'nullable|boolean',
            'emergencyVisit' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $log = TreatmentMonitoringLog::create([
            ...$validated,
            'treatmentPlanId' => $plan->treatmentPlanId,
            'recordedBy' => $user->id,
        ]);

        return response()->json(['message' => 'Monitoring entry recorded.', 'log' => $log], 201);
    }
}
