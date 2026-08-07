<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AwarenessRegistration;
use App\Models\Navigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigatorClientsController extends Controller
{
    /**
     * All clients assigned to the logged-in navigator, spanning both
     * pre-conversion registrations (Stage 1: Bloom awareness/assessment)
     * and full Client records (Stage 2+: journeyStage tracks the rest).
     * A single person may appear once — if they've converted to a Client,
     * we show their Client record, not the stale registration.
     */
    public function index(Request $request): JsonResponse
    {
        $navigator = Navigator::where('userId', $request->user()->id)
            ->where('isActive', true)
            ->first();

        if (!$navigator) {
            return response()->json(['message' => 'No active navigator profile for this user.'], 403);
        }

        $registrations = AwarenessRegistration::with(['selfAssessments' => fn ($q) => $q->latest('assessmentId')->limit(1), 'client'])
            ->where('navigatorId', $navigator->id)
            ->get();

        $clients = $registrations->map(function ($registration) {
            $latestAssessment = $registration->selfAssessments->first();

            // Already converted to a full Client — journeyStage is the
            // real source of truth from here on.
            if ($registration->client) {
                return [
                    'registrationId' => $registration->registrationId,
                    'clientId'       => $registration->client->clientId,
                    'fullName'       => $registration->fullName,
                    'phoneNumber'    => $registration->phoneNumber,
                    'riskCategory'   => $latestAssessment->riskCategory ?? null,
                    'stage'          => $registration->client->journeyStage,
                    'stageLabel'     => $this->humanizeStage($registration->client->journeyStage),
                ];
            }

            // Not yet converted — infer a pre-conversion pseudo-stage so
            // navigators can see who's stuck before Stage 2 intake.
            $stage = $latestAssessment ? 'assessed' : 'registered';

            return [
                'registrationId' => $registration->registrationId,
                'clientId'       => null,
                'fullName'       => $registration->fullName,
                'phoneNumber'    => $registration->phoneNumber,
                'riskCategory'   => $latestAssessment->riskCategory ?? null,
                'stage'          => $stage,
                'stageLabel'     => $this->humanizeStage($stage),
            ];
        });

        return response()->json(['clients' => $clients->values()]);
    }

    private function humanizeStage(?string $stage): string
    {
        return match ($stage) {
            'registered' => 'Registered (awaiting assessment)',
            'assessed' => 'Assessed — pending intake',
            default => $stage ? ucwords(str_replace(['_', '-'], ' ', $stage)) : 'Unknown',
        };
    }
}