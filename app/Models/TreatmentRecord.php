<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentRecord extends Model
{
    protected $primaryKey = 'treatmentRecordId';

    protected $fillable = [
        'treatmentPlanId',
        'modalityType',
        'startDate',
        'completionDate',
        'completionStatus',
        'reasonForDiscontinuation',
        'notes',
        'modalityDetails',
        'recordedBy',
    ];

    protected $casts = [
        'modalityDetails' => 'array',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatmentPlanId', 'treatmentPlanId');
    }
}
