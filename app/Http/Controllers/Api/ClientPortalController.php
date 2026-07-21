<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
    /**
     * The authenticated Client is set on the request by ClientTokenAuth
     * middleware — every method here scopes strictly to that client and
     * has no way to reach anyone else's record.
     */
    protected function client(Request $request)
    {
        return $request->attributes->get('authenticatedClient');
    }

    public function me(Request $request): JsonResponse
    {
        $client = $this->client($request);

        return response()->json([
            'client' => [
                'clientId' => $client->clientId,
                'fullName' => $client->fullName,
                'gender' => $client->gender,
                'dateOfBirth' => $client->dateOfBirth,
                'age' => $client->age,
                'phoneNumber' => $client->phoneNumber,
                'email' => $client->email,
                'address' => $client->address,
                'stateOfResidence' => $client->stateOfResidence,
                'lgaOfResidence' => $client->lgaOfResidence,
                'registrationDate' => $client->registrationDate,
                'facility' => $client->facility ? [
                    'facilityName' => $client->facility->facilityName,
                    'facilityAddress' => $client->facility->facilityAddress,
                    'phoneNumber' => $client->facility->phoneNumber,
                ] : null,
            ],
        ]);
    }

    public function riskProfile(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $profile = $client->latestRiskProfile;

        if (!$profile) {
            return response()->json(['riskProfile' => null]);
        }

        // Only surface fields a patient can meaningfully act on — skip
        // internal clinical coding (e.g. raw comorbidity JSON keys).
        return response()->json([
            'riskProfile' => [
                'familyHistory' => $profile->familyHistory,
                'smokingStatus' => $profile->smokingStatus,
                'alcoholConsumption' => $profile->alcoholConsumption,
                'weightKg' => $profile->weightKg,
                'heightCm' => $profile->heightCm,
                'bmi' => $profile->bmi,
                'recordedAt' => $profile->recordedAt,
            ],
        ]);
    }

    public function visits(Request $request): JsonResponse
    {
        $client = $this->client($request);

        $visits = $client->visits()
            ->with(['breastScreening', 'cervicalScreening', 'prostateScreening', 'colorectalScreening', 'liverScreening'])
            ->orderByDesc('visitDate')
            ->get()
            ->map(function ($visit) {
                return [
                    'visitId' => $visit->visitId,
                    'visitDate' => $visit->visitDate,
                    'visitType' => $visit->visitType,
                    'overallOutcome' => $visit->overallOutcome,
                    'outcomeNotes' => $visit->outcomeNotes,
                    'repeatScreeningDate' => $visit->repeatScreeningDate,
                    'screenings' => collect([
                        'breast' => $visit->breastScreening,
                        'cervical' => $visit->cervicalScreening,
                        'prostate' => $visit->prostateScreening,
                        'colorectal' => $visit->colorectalScreening,
                        'liver' => $visit->liverScreening,
                    ])->filter()->map(fn ($s) => [
                        'screeningDate' => $s->screeningDate ?? null,
                        'screeningResult' => $s->screeningResult ?? null,
                    ]),
                ];
            });

        return response()->json(['visits' => $visits]);
    }

    public function outcome(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $outcome = $client->outcome;

        if (!$outcome) {
            return response()->json(['outcome' => null]);
        }

        // Patient-facing summary only — internal clinical detail (staging
        // comments, adherence tracking, etc.) stays out of the portal.
        return response()->json([
            'outcome' => [
                'screeningResult' => $outcome->screeningResult,
                'cancerConfirmed' => $outcome->cancerConfirmed,
                'linkageToTreatment' => $outcome->linkageToTreatment,
                'treatmentFacility' => $outcome->treatmentFacility,
                'treatmentCompleted' => $outcome->treatmentCompleted,
                'nextFollowUpDate' => $outcome->nextFollowUpDate,
            ],
        ]);
    }
}
