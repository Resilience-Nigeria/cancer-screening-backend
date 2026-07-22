<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentMonitoringLog extends Model
{
    protected $primaryKey = 'monitoringLogId';

    protected $fillable = [
        'treatmentPlanId',
        'logDate',
        'attended',
        'missedAppointment',
        'toxicity',
        'labResults',
        'imagingResponse',
        'clinicalResponse',
        'doseModification',
        'treatmentInterruption',
        'hospitalAdmission',
        'emergencyVisit',
        'notes',
        'recordedBy',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'missedAppointment' => 'boolean',
        'treatmentInterruption' => 'boolean',
        'hospitalAdmission' => 'boolean',
        'emergencyVisit' => 'boolean',
        'labResults' => 'array',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatmentPlanId', 'treatmentPlanId');
    }
}
