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

        // Urinary symptoms
        'poorUrinaryStream',
        'urgeIncontinence',
        'delayStartingUrination',
        'inabilityToHoldUrine',
        'terminalDribbling',
        'frequentDayUrination',
        'nocturia',
        'incompleteEmptying',
        'bloodInUrine',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'biopsyDone' => 'boolean',
        'ipssScore' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}