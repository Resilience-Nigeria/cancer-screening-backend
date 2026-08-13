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

        // Ablation eligibility + booking (mirrors breast's biopsyBookNow pattern)
        'ablationEligibility',
        'ablationBookNow',
        'ablationBookingDate',
        'ablationBookingFacilityId',
        'ablationBookingNotes',

        // LEEP booking + post-treatment histology
        'leepBookNow',
        'leepBookingDate',
        'leepBookingFacilityId',
        'leepBookingNotes',
        'histologyResult',

        // Colposcopy booking (for non-16/18/35/45 high-risk genotypes)
        'colposcopyBookNow',
        'colposcopyBookingDate',
        'colposcopyBookingFacilityId',
        'colposcopyBookingNotes',
        'colposcopyResult',

        // Derived outcome
        'referralPathway',
        'followUpMonths',

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
        'ablationBookNow' => 'boolean',
        'ablationBookingDate' => 'date',
        'leepBookNow' => 'boolean',
        'leepBookingDate' => 'date',
        'colposcopyBookNow' => 'boolean',
        'colposcopyBookingDate' => 'date',
        'ageAtFirstIntercourse' => 'integer',
        'numberOfChildbirths' => 'integer',
        'followUpMonths' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId', 'visitId');
    }
}