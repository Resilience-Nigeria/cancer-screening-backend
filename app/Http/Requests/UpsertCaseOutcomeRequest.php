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
            'cancerConfirmed' => ['required', 'boolean'],
            'cancerType' => ['nullable', 'string', 'max:255'],
            'stageAtDiagnosis' => ['nullable', 'string', 'max:255'],
            'diagnosisDate' => ['nullable', 'date'],
            'linkageToTreatment' => ['required', 'boolean'],
            'treatmentTacility' => ['nullable', 'string', 'max:255'],
            'treatmentInitiated' => ['nullable', 'date'],
            'treatmentCompleted' => ['required', 'boolean'],
            'treatmentOutcome' => ['nullable', 'in:complete_remission,partial_remission,stable_disease,progressive_disease'],
            'followUpStatus' => ['nullable', 'in:disease_free,recurrence,long_term_survival_with_chronic_disease,treatment_related_complications'],
        ];
    }
}