<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOutcome extends Model
{
    protected $table = 'case_outcomes';
    
    protected $fillable = [
        'clientId',
        
        // Backend compatibility fields
        'cancerConfirmed',
        'linkageToTreatment',
        'treatmentCompleted',
        
        // Pre-screening counseling
        'preScreeningCounselingDate',
        'preScreeningCounselor',
        'preScreeningConsent',
        
        // Screening outcome
        'screeningResult',
        'screeningDate',
        
        // Post-screening counseling
        'postScreeningCounselingDate',
        'postScreeningCounselor',
        
        // Follow-up
        'nextFollowUpDate',
        'followUpEstablished',
        
        // Diagnosis & staging
        'diagnosis',
        'cancerType',
        'cancerStage',
        'stagingComments',
        'diagnosisDate',
        
        // Treatment commencement
        'treatmentCommenced',
        'treatmentCommencementDate',
        'treatmentDelayReason',
        
        // Treatment details
        'treatmentType',
        'treatmentFacility',
        
        // Adherence
        'adherenceRating',
        'missedAppointments',
        'missedAppointmentReasons',
        'adherenceInterventions',
        
        // Treatment completion
        'treatmentStatus',
        'treatmentCompletionDate',
        'discontinuationReason',
        'treatmentDuration',
        
        // Outcome
        'clinicalOutcome',
        'outcomeAssessmentDate',
        
        // General
        'remarks',
    ];

    protected $casts = [
        'missedAppointmentReasons' => 'array',
        'adherenceInterventions' => 'array',
        'preScreeningCounselingDate' => 'date',
        'screeningDate' => 'date',
        'postScreeningCounselingDate' => 'date',
        'nextFollowUpDate' => 'date',
        'diagnosisDate' => 'date',
        'treatmentCommencementDate' => 'date',
        'treatmentCompletionDate' => 'date',
        'outcomeAssessmentDate' => 'date',
        'missedAppointments' => 'integer',
    ];

    /**
     * Get the client that owns the outcome
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }
}