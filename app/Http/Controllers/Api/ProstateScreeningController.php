<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProstateScreeningRequest;
use App\Models\ProstateScreening;
use App\Models\ScreeningVisit;
use App\Services\StoresServiceBookings;
use Illuminate\Http\JsonResponse;

class ProstateScreeningController extends Controller
{
    use StoresServiceBookings;

    private const IPSS_ITEMS = [
        'ipssIncompleteEmptying', 'ipssFrequency', 'ipssIntermittency', 'ipssUrgency',
        'ipssWeakStream', 'ipssStraining', 'ipssNocturia',
    ];

    private const FLOAT_FIELDS = [
        'psaLevel', 'repeatPsaLevel', 'previousPsaLevel', 'prostateVolumeCc',
        'psaDensity', 'freePsaPercent', 'psaVelocity',
    ];

    private const INT_FIELDS = [
        'ipssScore', 'ageAtScreening', 'ipssQualityOfLife',
        'recallBookingFacilityId', 'urologyBookingFacilityId',
    ];

    /**
     * Store or update prostate screening for a visit
     */
    public function store(StoreProstateScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $validated = $request->validated();

        // Convert empty strings to null
        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        // Numeric conversions
        foreach (self::FLOAT_FIELDS as $k) {
            if (isset($validated[$k])) {
                $validated[$k] = (float) $validated[$k];
            }
        }
        foreach (self::INT_FIELDS as $k) {
            if (isset($validated[$k])) {
                $validated[$k] = (int) $validated[$k];
            }
        }

        // I-PSS: the total and severity are recomputed here once all seven items are answered,
        // so the stored score can't disagree with the answers.
        $ipssAnswers = array_map(fn ($k) => $validated[$k] ?? null, self::IPSS_ITEMS);
        if (!in_array(null, $ipssAnswers, true)) {
            $validated['ipssScore'] = array_sum(array_map('intval', $ipssAnswers));
        }
        if (isset($validated['ipssScore'])) {
            $s = (int) $validated['ipssScore'];
            $validated['ipssSeverity'] = $s <= 7 ? 'Mild' : ($s <= 19 ? 'Moderate' : 'Severe');
        }

        $screening = ProstateScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$validated,
                'visitId' => $visit->visitId,
                'clientId' => $visit->clientId,
            ]
        );

        // Persist the actual bookings (same pattern as the cervical module). If your
        // StoresServiceBookings trait restricts the allowed service names, add
        // 'psa_recall' and 'urology' to it.
        if ($validated['recallBookNow'] ?? false) {
            $this->storeBooking(
                $visit->clientId,
                $visit->visitId,
                'psa_recall',
                'prostate',
                $validated['recallBookingDate'] ?? null,
                $validated['recallBookingFacilityId'] ?? null,
                $validated['recallBookingNotes'] ?? null
            );
        }

        if ($validated['urologyBookNow'] ?? false) {
            $this->storeBooking(
                $visit->clientId,
                $visit->visitId,
                'urology',
                'prostate',
                $validated['urologyBookingDate'] ?? null,
                $validated['urologyBookingFacilityId'] ?? null,
                $validated['urologyBookingNotes'] ?? null
            );
        }

        return response()->json([
            'message' => 'Prostate screening saved successfully',
            'data' => $this->formatScreening($screening),
        ], 201);
    }

    /**
     * Get prostate screening for a visit
     */
    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = $visit->prostateScreening;

        return response()->json([
            'data' => $screening ? $this->formatScreening($screening) : null,
        ]);
    }

    /**
     * Format screening data for API response (camelCase for frontend)
     */
    protected function formatScreening(ProstateScreening $screening): array
    {
        return [
            'screeningId' => $screening->screeningId,
            'visitId' => $screening->visitId,
            'clientId' => $screening->clientId,
            'screeningDate' => optional($screening->screeningDate)->toDateString(),
            'screeningResult' => $screening->screeningResult,

            // Eligibility, consent, shared decision
            'ageAtScreening' => $screening->ageAtScreening,
            'consentGiven' => $screening->consentGiven,
            'counsellingDone' => $screening->counsellingDone,
            'sharedDecision' => $screening->sharedDecision,

            // Primary tests
            'psaTestDate' => optional($screening->psaTestDate)->toDateString(),
            'psaLevel' => $screening->psaLevel,
            'repeatPsaLevel' => $screening->repeatPsaLevel,
            'repeatPsaDate' => optional($screening->repeatPsaDate)->toDateString(),
            'previousPsaLevel' => $screening->previousPsaLevel,
            'previousPsaDate' => optional($screening->previousPsaDate)->toDateString(),
            'dreFinding' => $screening->dreFinding,
            'dreResult' => $screening->dreResult,

            // I-PSS
            'ipssIncompleteEmptying' => $screening->ipssIncompleteEmptying,
            'ipssFrequency' => $screening->ipssFrequency,
            'ipssIntermittency' => $screening->ipssIntermittency,
            'ipssUrgency' => $screening->ipssUrgency,
            'ipssWeakStream' => $screening->ipssWeakStream,
            'ipssStraining' => $screening->ipssStraining,
            'ipssNocturia' => $screening->ipssNocturia,
            'ipssScore' => $screening->ipssScore,
            'ipssSeverity' => $screening->ipssSeverity,
            'ipssQualityOfLife' => $screening->ipssQualityOfLife,

            // Warning symptoms
            'poorUrinaryStream' => $screening->poorUrinaryStream,
            'urgeIncontinence' => $screening->urgeIncontinence,
            'delayStartingUrination' => $screening->delayStartingUrination,
            'inabilityToHoldUrine' => $screening->inabilityToHoldUrine,
            'terminalDribbling' => $screening->terminalDribbling,
            'frequentDayUrination' => $screening->frequentDayUrination,
            'nocturia' => $screening->nocturia,
            'incompleteEmptying' => $screening->incompleteEmptying,
            'bloodInUrine' => $screening->bloodInUrine,
            'inabilityToPassUrine' => $screening->inabilityToPassUrine,
            'bonePainHipBack' => $screening->bonePainHipBack,
            'unexplainedWeightLoss' => $screening->unexplainedWeightLoss,

            'screeningPathway' => $screening->screeningPathway,

            // PSA deferment
            'recentDre' => $screening->recentDre,
            'recentEjaculation' => $screening->recentEjaculation,
            'recentUrinaryInfection' => $screening->recentUrinaryInfection,
            'recentVigorousExercise' => $screening->recentVigorousExercise,
            'psaEligibility' => $screening->psaEligibility,
            'recallDate' => optional($screening->recallDate)->toDateString(),

            // Result, recall and bookings
            'psaCategory' => $screening->psaCategory,
            'recallInterval' => $screening->recallInterval,
            'nextScreeningDate' => optional($screening->nextScreeningDate)->toDateString(),
            'recommendedAction' => $screening->recommendedAction,
            'recallBookNow' => (bool) $screening->recallBookNow,
            'recallBookingDate' => optional($screening->recallBookingDate)->toDateString(),
            'recallBookingFacilityId' => $screening->recallBookingFacilityId,
            'recallBookingNotes' => $screening->recallBookingNotes,
            'urologyBookNow' => (bool) $screening->urologyBookNow,
            'urologyBookingDate' => optional($screening->urologyBookingDate)->toDateString(),
            'urologyBookingFacilityId' => $screening->urologyBookingFacilityId,
            'urologyBookingNotes' => $screening->urologyBookingNotes,

            // Secondary risk assessment
            'prostateVolumeCc' => $screening->prostateVolumeCc,
            'psaDensity' => $screening->psaDensity,
            'freePsaPercent' => $screening->freePsaPercent,
            'psaVelocity' => $screening->psaVelocity,
            'mpMriResult' => $screening->mpMriResult,
            'priorNegativeBiopsy' => $screening->priorNegativeBiopsy,
            'highClinicalSuspicion' => $screening->highClinicalSuspicion,
            'biopsyIndicated' => $screening->biopsyIndicated,
            'biopsyIndicationReasons' => $screening->biopsyIndicationReasons,

            'biopsyDone' => (bool) $screening->biopsyDone,
            'gleasonScore' => $screening->gleasonScore,
            'treatmentReferral' => $screening->treatmentReferral,
            'remarks' => $screening->remarks,
        ];
    }

    /**
     * Authorize that the user can access this visit
     */
    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($visit->facilityId)) {
            abort(403, 'You cannot access this visit');
        }
    }
}