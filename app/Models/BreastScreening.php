<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreastScreening extends Model
{
    protected $table = 'breast_screenings';

    protected $primaryKey = 'screeningId';

    protected $fillable = [
        'clientId',
        'visitId',
        'method',
        'screeningDate',
        'screeningResult',

        // Imaging findings
        'biradsScore',
        'breastDensity',

        // Breast health history & symptoms
        'breastfeedingHistory',
        'breastfeedingDuration',
        'breastLumps',
        'breastNippleDischarge',
        'dischargeType',
        'skinChanges',
        'breastPain',
        'previousBreastSurgery',
        'previousBiopsy',
        'ageAtFirstMenstruation',
        'ageAtMenopause',

        // Procedures & follow-up
        'biopsyDone',
        'biopsyResult',
        'referralCompleted',
        'treatmentReferral',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'biopsyDone' => 'boolean',
        'referralCompleted' => 'boolean',
        'breastfeedingDuration' => 'integer',
        'ageAtFirstMenstruation' => 'integer',
        'ageAtMenopause' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}