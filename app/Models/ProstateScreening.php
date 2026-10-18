<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProstateScreening extends Model
{
    protected $table = 'prostate_screenings';

    protected $primaryKey = 'screeningId';

    protected $fillable = [
        'clientId',
        'visitId',
        'screeningDate',
        'screeningResult',
        'psaLevel',
        'dreResult',
        'ipssScore',
        'biopsyDone',
        'gleasonScore',
        'treatmentReferral',

        // Urinary / warning symptoms
        'poorUrinaryStream',
        'urgeIncontinence',
        'delayStartingUrination',
        'inabilityToHoldUrine',
        'terminalDribbling',
        'frequentDayUrination',
        'nocturia',
        'incompleteEmptying',
        'bloodInUrine',
        'inabilityToPassUrine',
        'bonePainHipBack',
        'unexplainedWeightLoss',

        'screeningPathway',

        // PSA-deferment conditions and eligibility outcome
        'recentDre',
        'recentEjaculation',
        'recentUrinaryInfection',
        'recentVigorousExercise',
        'psaEligibility',
        'recallDate',
        'recommendedAction',

        // Step 1 — eligibility; Steps 2–3 — consent, counselling, shared decision
        'ageAtScreening',
        'consentGiven',
        'counsellingDone',
        'sharedDecision',

        // Step 4 — primary tests
        'psaTestDate',
        'repeatPsaLevel',
        'repeatPsaDate',
        'dreFinding',
        'previousPsaLevel',
        'previousPsaDate',

        // Step 5/6 — recall schedule and bookings
        'nextScreeningDate',
        'psaCategory',
        'recallInterval',
        'recallBookNow',
        'recallBookingDate',
        'recallBookingFacilityId',
        'recallBookingNotes',
        'urologyBookNow',
        'urologyBookingDate',
        'urologyBookingFacilityId',
        'urologyBookingNotes',

        // Step 7 — secondary risk assessment
        'prostateVolumeCc',
        'psaDensity',
        'freePsaPercent',
        'psaVelocity',
        'mpMriResult',
        'priorNegativeBiopsy',
        'highClinicalSuspicion',
        'biopsyIndicated',
        'biopsyIndicationReasons',

        // I-PSS
        'ipssIncompleteEmptying',
        'ipssFrequency',
        'ipssIntermittency',
        'ipssUrgency',
        'ipssWeakStream',
        'ipssStraining',
        'ipssNocturia',
        'ipssSeverity',
        'ipssQualityOfLife',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'recallDate' => 'date',
        'psaTestDate' => 'date',
        'repeatPsaDate' => 'date',
        'previousPsaDate' => 'date',
        'nextScreeningDate' => 'date',
        'recallBookingDate' => 'date',
        'urologyBookingDate' => 'date',
        'biopsyDone' => 'boolean',
        'recallBookNow' => 'boolean',
        'urologyBookNow' => 'boolean',
        'ipssScore' => 'integer',
        'ageAtScreening' => 'integer',
        'ipssIncompleteEmptying' => 'integer',
        'ipssFrequency' => 'integer',
        'ipssIntermittency' => 'integer',
        'ipssUrgency' => 'integer',
        'ipssWeakStream' => 'integer',
        'ipssStraining' => 'integer',
        'ipssNocturia' => 'integer',
        'ipssQualityOfLife' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}