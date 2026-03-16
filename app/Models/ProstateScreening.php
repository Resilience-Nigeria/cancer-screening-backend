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
        'result',
        'psaLevel',
        'dreResult',
        'ipssScore',
        'biopsyDone',
        'gleasonScore',
        'treatmentReferral',
        'treatmentProvided',
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