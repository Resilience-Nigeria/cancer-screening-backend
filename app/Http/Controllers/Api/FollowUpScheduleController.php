<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUpSchedule;
use App\Models\TreatmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpScheduleController extends Controller
{
    /**
     * A care-coordinator-style view across all of a user's visible
     * facilities — due/overdue follow-ups, for the "care coordinator
     * dashboard for high-risk patients" called for in 4.11.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $user->visibleFacilityIds();

        $schedules = FollowUpSchedule::with(['treatmentPlan.client', 'treatmentPlan.facility'])
            ->whereHas('treatmentPlan', function ($q) use ($visibleIds) {
                if ($visibleIds !== null) {
                    $q->whereIn('facilityId', $visibleIds);
                }
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('dueDate')
            ->paginate(20);

        return response()->json($schedules);
    }

    public function markCompleted(Request $request, FollowUpSchedule $schedule): JsonResponse
    {
        if (!$request->user()->canAccessFacility($schedule->treatmentPlan->facilityId)) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate([
            'completedDate' => 'required|date',
            'completionNotes' => 'nullable|string',
        ]);

        $schedule->update([...$validated, 'status' => 'completed']);

        return response()->json(['message' => 'Follow-up marked completed.', 'schedule' => $schedule]);
    }
}
