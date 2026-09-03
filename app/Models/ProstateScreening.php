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

        // Urinary / warning symptoms (existing 9 + 3 red-flag symptoms added
        // per the National Prostate Cancer Screening Algorithm)
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

        // Screening pathway (symptomatic → clinical/diagnostic referral vs
        // asymptomatic → continue routine screening)
        'screeningPathway',

        // PSA-deferment conditions and eligibility outcome
        'recentDre',
        'recentEjaculation',
        'recentUrinaryInfection',
        'recentVigorousExercise',
        'psaEligibility',
        'recallDate',
        'recommendedAction',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'recallDate' => 'date',
        'biopsyDone' => 'boolean',
        'ipssScore' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}