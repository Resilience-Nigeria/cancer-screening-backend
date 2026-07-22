<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPlan extends Model
{
    protected $primaryKey = 'treatmentPlanId';

    protected $fillable = [
        'clientId',
        'evaluationId',
        'facilityId',
        'performanceStatusScale',
        'performanceStatusValue',
        'comorbidities',
        'patientPreferencesNotes',
        'consentObtained',
        'consentDate',
        'decisionPathway',
        'managementNotes',
        'routineRecallDate',
        'procedurePerformed',
        'procedureComplications',
        'surveillanceNotes',
        'tStage',
        'nStage',
        'mStage',
        'clinicalStage',
        'histologicalType',
        'tumourGrade',
        'biomarkers',
        'mdtParticipants',
        'mdtDate',
        'mdtDecisionNotes',
        'clinicalTrialEligible',
        'treatmentIntent',
        'treatmentOutcome',
        'outcomeDate',
        'outcomeNotes',
        'survivorshipPlan',
        'status',
        'createdBy',
    ];

    protected $casts = [
        'consentObtained' => 'boolean',
        'clinicalTrialEligible' => 'boolean',
        'biomarkers' => 'array',
        'mdtParticipants' => 'array',
        'survivorshipPlan' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(DiagnosticEvaluation::class, 'evaluationId', 'evaluationId');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    public function treatmentRecords(): HasMany
    {
        return $this->hasMany(TreatmentRecord::class, 'treatmentPlanId', 'treatmentPlanId');
    }

    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(TreatmentMonitoringLog::class, 'treatmentPlanId', 'treatmentPlanId');
    }

    public function followUpSchedules(): HasMany
    {
        return $this->hasMany(FollowUpSchedule::class, 'treatmentPlanId', 'treatmentPlanId');
    }
}
