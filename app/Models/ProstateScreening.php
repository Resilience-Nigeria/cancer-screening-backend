<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProstateScreening extends Model
{
    protected $table = 'prostate_screenings';

    protected $primaryKey = 'screeningId';
    protected $fillable = [
        'visitId',
        'method',
        'screeningDate',
        'screeningResult',
        'psaLevel',
        'dreResult',
        'ipssScore',
        'biopsyDone',
        'gleasonScore',
        'treatmentReferral',
        'treatmentProvided',

        // Urinary Symptoms
        'poorUrinaryStream',
        'urgeIncontinence',
        'delayStartingUrination',
        'inabilityToHoldUrine',
        'terminalDribbling',
        'frequentDayUrination',
        'nocturia',
        'incompleteEmptying',
        'bloodInUrine',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'biopsyDone' => 'boolean',
        'treatmentProvided' => 'boolean',
    ];


    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId');
    }
}