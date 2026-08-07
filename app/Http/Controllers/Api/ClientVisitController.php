<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientVisitController extends Controller
{
    private const SCREENING_RELATIONS = [
        'breast'     => 'breastScreening',
        'cervical'   => 'cervicalScreening',
        'colorectal' => 'colorectalScreening',
        'liver'      => 'liverScreening',
        'prostate'   => 'prostateScreening',
    ];

    /**
     * Visits not yet through the Outcome step, each with a computed
     * resumeStep so the Stage 2 wizard can drop the user back exactly
     * where they left off instead of restarting from Registration.
     */
    public function incomplete(string $clientId): JsonResponse
    {
        $client = Client::with('latestRiskProfile')->where('clientId', $clientId)->firstOrFail();

        $visits = $client->visits()
            ->whereNull('overallOutcome')
            ->with([
                'examination',
                'breastScreening', 'cervicalScreening',
                'colorectalScreening', 'liverScreening', 'prostateScreening',
            ])
            ->latest('visitDate')
            ->get();

        $hasRiskProfile = (bool) $client->latestRiskProfile;

        $result = $visits->map(function ($visit) use ($hasRiskProfile) {
            $screenings = [];
            $anyResultMissing = false;
            $anyRecordExists = false;

            foreach (self::SCREENING_RELATIONS as $type => $relation) {
                $record = $visit->{$relation};
                if ($record) {
                    $anyRecordExists = true;
                    $screenings[$type] = $record;
                    if ($record->screeningResult === null) {
                        $anyResultMissing = true;
                    }
                }
            }

            $resumeStep = match (true) {
                !$hasRiskProfile => 'riskVerify',
                !$visit->examination => 'physicalExam',
                !$anyRecordExists => 'cancerTypeSelect',
                $anyResultMissing => 'screening',
                default => 'outcome',
            };

            return [
                'visitId'     => $visit->visitId,
                'visitDate'   => $visit->visitDate,
                'visitType'   => $visit->visitType,
                'resumeStep'  => $resumeStep,
                'cancerTypes' => array_keys($screenings),
                'screenings'  => $screenings,
                'examination' => $visit->examination,
            ];
        });

        return response()->json([
            'client'        => $client,
            'riskProfile'   => $client->latestRiskProfile,
            'visits'        => $result->values(),
        ]);
    }
}