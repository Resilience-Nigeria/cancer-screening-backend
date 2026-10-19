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
     *
     * Stage 2 order is now: Cancer Type(s) -> Risk Assessment -> Physical Exam ->
     * Screening -> Outcome. Cancer types are only stored once a screening record
     * is saved, so a visit with no screening record yet resumes at the cancer-type
     * step (the risk profile and exam are pre-filled from what was already saved).
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
                !$anyRecordExists => 'cancerTypeSelect',
                !$hasRiskProfile => 'riskVerify',
                !$visit->examination => 'physicalExam',
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