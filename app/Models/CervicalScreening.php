<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CervicalScreening extends Model
{
    protected $table = 'cervical_screenings';

    protected $primaryKey = 'screeningId';

    protected $fillable = [
        'clientId',
        'visitId',
        'method',
        'screeningDate',
        'screeningResult',
        'hpvResult',
        'hpvGenotype',
        'colposcopyDone',
        'biopsyDone',
        'biopsyResult',
        'treatmentReferral',

        // Cervical-specific risk factors
        'moreThanOnePartner',
        'ageAtFirstIntercourse',
        'numberOfChildbirths',
        'contraceptiveUse',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'colposcopyDone' => 'boolean',
        'biopsyDone' => 'boolean',
        'ageAtFirstIntercourse' => 'integer',
        'numberOfChildbirths' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}