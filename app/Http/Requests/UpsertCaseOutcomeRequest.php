<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCaseOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ============================================
            // BACKWARD COMPATIBILITY FIELDS (Auto-derived from new fields)
            // ============================================
            'cancerConfirmed' => ['nullable', 'in:yes,no'],
            'linkageToTreatment' => ['nullable', 'in:yes,no'],
            'treatmentCompleted' => ['nullable', 'in:yes,no,ongoing'],
            
            // ============================================
            // 1. PRE-SCREENING COUNSELING
            // ============================================
            'preScreeningCounselingDate' => ['nullable', 'date'],
            'preScreeningCounselor' => ['nullable', 'string', 'max:255'],
            'preScreeningConsent' => ['nullable', 'in:yes,no'],
            
            // ============================================
            // 2. SCREENING OUTCOME
            // ============================================
            'screeningResult' => ['nullable', 'in:negative,positive,inconclusive'],
            'screeningDate' => ['nullable', 'date'],
            
            // ============================================
            // 3. POST-SCREENING COUNSELING
            // ============================================
            'postScreeningCounselingDate' => ['nullable', 'date'],
            'postScreeningCounselor' => ['nullable', 'string', 'max:255'],
            
            // ============================================
            // 4. FOLLOW-UP (For Negative Results)
            // ============================================
            'nextFollowUpDate' => ['nullable', 'date'],
            'followUpEstablished' => ['nullable', 'in:yes,no'],
            
            // ============================================
            // 5. DIAGNOSIS & STAGING
            // ============================================
            'diagnosis' => ['nullable', 'string', 'max:500'],
            'cancerType' => ['nullable', 'string', 'max:255'],
            'cancerStage' => ['nullable', 'string', 'max:50'],
            'stagingComments' => ['nullable', 'string', 'max:2000'],
            'diagnosisDate' => ['nullable', 'date'],
            
            // ============================================
            // 6. TREATMENT COMMENCEMENT
            // ============================================
            'treatmentCommenced' => ['nullable', 'in:yes,no'],
            'treatmentCommencementDate' => ['nullable', 'date'],
            'treatmentDelayReason' => ['nullable', 'string', 'max:255'],
            
            // ============================================
            // 7. TREATMENT DETAILS
            // ============================================
            'treatmentType' => ['nullable', 'string', 'max:255'],
            'treatmentFacility' => ['nullable', 'string', 'max:255'],
            
            // ============================================
            // 8. ADHERENCE TO TREATMENT
            // ============================================
            'adherenceRating' => ['nullable', 'string', 'max:50'],
            'missedAppointments' => ['nullable', 'integer', 'min:0'],
            'missedAppointmentReasons' => ['nullable', 'array'],
            'missedAppointmentReasons.*' => ['string', 'max:255'],
            'adherenceInterventions' => ['nullable', 'array'],
            'adherenceInterventions.*' => ['string', 'max:255'],
            
            // ============================================
            // 9. TREATMENT COMPLETION STATUS
            // ============================================
            'treatmentStatus' => ['nullable', 'string', 'max:255'],
            'treatmentCompletionDate' => ['nullable', 'date'],
            'discontinuationReason' => ['nullable', 'string', 'max:255'],
            'treatmentDuration' => ['nullable', 'string', 'max:100'],
            
            // ============================================
            // 10. CLINICAL OUTCOME
            // ============================================
            'clinicalOutcome' => ['nullable', 'string', 'max:255'],
            'outcomeAssessmentDate' => ['nullable', 'date'],
            
            // ============================================
            // GENERAL
            // ============================================
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Handle missedAppointments conversion
        if ($this->has('missedAppointments')) {
            $missedAppointments = $this->input('missedAppointments');
            if (is_string($missedAppointments)) {
                $this->merge([
                    'missedAppointments' => (int) $missedAppointments
                ]);
            }
        }

        // Auto-calculate derived fields from frontend data
        $derivedFields = [];
        
        // cancerConfirmed: yes if screeningResult is positive
        if ($this->has('screeningResult')) {
            $derivedFields['cancerConfirmed'] = $this->input('screeningResult') === 'positive' ? 'yes' : 'no';
        }
        
        // linkageToTreatment: yes if treatmentCommenced is yes
        if ($this->has('treatmentCommenced')) {
            $derivedFields['linkageToTreatment'] = $this->input('treatmentCommenced');
        }
        
        // treatmentCompleted: derived from treatmentStatus
        if ($this->has('treatmentStatus')) {
            $status = $this->input('treatmentStatus');
            if ($status === 'completed') {
                $derivedFields['treatmentCompleted'] = 'yes';
            } elseif ($status === 'discontinued') {
                $derivedFields['treatmentCompleted'] = 'no';
            } elseif (in_array($status, ['in_progress_on_schedule', 'in_progress_behind'])) {
                $derivedFields['treatmentCompleted'] = 'ongoing';
            }
        }
        
        $this->merge($derivedFields);
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'preScreeningCounselingDate' => 'pre-screening counseling date',
            'preScreeningCounselor' => 'pre-screening counselor',
            'preScreeningConsent' => 'pre-screening consent',
            'screeningResult' => 'screening result',
            'screeningDate' => 'screening date',
            'postScreeningCounselingDate' => 'post-screening counseling date',
            'postScreeningCounselor' => 'post-screening counselor',
            'nextFollowUpDate' => 'next follow-up date',
            'followUpEstablished' => 'follow-up established',
            'diagnosis' => 'diagnosis',
            'cancerType' => 'cancer type',
            'cancerStage' => 'cancer stage',
            'stagingComments' => 'staging comments',
            'diagnosisDate' => 'diagnosis date',
            'treatmentCommenced' => 'treatment commenced',
            'treatmentCommencementDate' => 'treatment commencement date',
            'treatmentDelayReason' => 'treatment delay reason',
            'treatmentType' => 'treatment type',
            'treatmentFacility' => 'treatment facility',
            'adherenceRating' => 'adherence rating',
            'missedAppointments' => 'missed appointments',
            'missedAppointmentReasons' => 'missed appointment reasons',
            'adherenceInterventions' => 'adherence interventions',
            'treatmentStatus' => 'treatment status',
            'treatmentCompletionDate' => 'treatment completion date',
            'discontinuationReason' => 'discontinuation reason',
            'treatmentDuration' => 'treatment duration',
            'clinicalOutcome' => 'clinical outcome',
            'outcomeAssessmentDate' => 'outcome assessment date',
            'remarks' => 'remarks',
        ];
    }
}