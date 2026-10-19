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

        // Laterality — left/right findings for CBE and imaging
        'leftCbeFinding',
        'rightCbeFinding',
        'leftBiradsScore',
        'rightBiradsScore',
        'leftBreastDensity',
        'rightBreastDensity',
        'leftImagingFinding',
        'rightImagingFinding',

        // Breast health history & symptoms
        'breastfeedingHistory',
        'breastfeedingDuration',
        'breastLumps',
        'breastNippleDischarge',
        'dischargeType',
        'skinChanges',
        'breastPain',
        'previousBreastSurgery', // legacy — no longer collected by the UI
        'previousBiopsy',
        'ageAtFirstMenstruation',
        'ageAtMenopause',

        // Procedures & follow-up
        'biopsyDone',
        'biopsyBookingDate',
        'biopsyBookingFacilityId',
        'biopsyBookingNotes',
        'biopsyResult',
        'histologyResult',
        'ihcRequested',
        'ihcResult',
        'ihcBookingDate',
        'ihcBookingFacilityId',
        'ihcBookingNotes',
        'referralCompleted',
        'treatmentReferral',

        'remarks',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'biopsyDone' => 'boolean',
        'ihcRequested' => 'boolean',
        'biopsyBookingDate' => 'date',
        'ihcBookingDate' => 'date',
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