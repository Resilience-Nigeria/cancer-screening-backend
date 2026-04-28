<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreastScreening extends Model
{
    protected $table = 'breast_screenings';

    protected $primaryKey = 'screeningId';

    protected $fillable = [
        'visitId',
        'method',
        'screeningDate',
        'screeningResult',
        'hpvResult',
        'hpvGenotype',
        'colposcopyDone',
        'biopsyDone',
        'histologyResult',
        'treatmentProvided',
        'treatmentReferral',
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