<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiverScreening extends Model
{
    protected $table = 'liver_screenings';

    protected $primaryKey = 'screeningId';

    protected $fillable = [
        'clientId',
        'visitId',
        'method',
        'screeningDate',
        'screeningResult',
        'hbvStatus',
        'hcvStatus',
        'afpValue',
        'lesionDetected',
        'treatmentReferral',
        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'lesionDetected' => 'boolean',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}