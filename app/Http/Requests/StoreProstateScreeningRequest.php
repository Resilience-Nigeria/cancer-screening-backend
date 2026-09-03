<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProstateScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ];

        // Accept legacy "referral" key as an alias for "treatmentReferral".
        if ($this->has('referral') && !$this->filled('treatmentReferral')) {
            $merge['treatmentReferral'] = $this->input('referral');
        }

        if ($this->has('biopsyDone') && is_string($this->input('biopsyDone'))) {
            $merge['biopsyDone'] = filter_var($this->input('biopsyDone'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            // Screening details
            'clientId' => ['nullable', 'string'],
            'screeningDate' => ['nullable', 'date'],
            // "screeningResult" and "treatmentReferral" are auto-derived on the
            // client from the symptom checklist + PSA/DRE thresholds (National
            // Prostate Cancer Screening Algorithm) rather than picked manually,
            // but the values submitted still have to be one of these.
            'screeningResult' => ['nullable', 'in:negative,positive,suspicious,non_suspicious'],
            'psaLevel' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            // Column is enum('positive','negative')
            'dreResult' => ['nullable', 'in:positive,negative'],
            'ipssScore' => ['nullable', 'integer', 'min:0', 'max:35'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],

            // Urinary / warning symptoms (existing 9 + 3 added per the
            // screening algorithm's red-flag symptom list).
            'poorUrinaryStream' => ['nullable', 'in:yes,no'],
            'urgeIncontinence' => ['nullable', 'in:yes,no'],
            'delayStartingUrination' => ['nullable', 'in:yes,no'],
            'inabilityToHoldUrine' => ['nullable', 'in:yes,no'],
            'terminalDribbling' => ['nullable', 'in:yes,no'],
            'frequentDayUrination' => ['nullable', 'in:yes,no'],
            'nocturia' => ['nullable', 'in:yes,no'],
            'incompleteEmptying' => ['nullable', 'in:yes,no'],
            'bloodInUrine' => ['nullable', 'in:yes,no'],
            'inabilityToPassUrine' => ['nullable', 'in:yes,no'],
            'bonePainHipBack' => ['nullable', 'in:yes,no'],
            'unexplainedWeightLoss' => ['nullable', 'in:yes,no'],

            // Screening pathway routing (symptomatic vs asymptomatic), stored
            // for audit purposes alongside the symptom checklist above.
            'screeningPathway' => ['nullable', 'in:Symptomatic,Asymptomatic'],

            // PSA-deferment conditions and eligibility outcome.
            'recentDre' => ['nullable', 'in:yes,no'],
            'recentEjaculation' => ['nullable', 'in:yes,no'],
            'recentUrinaryInfection' => ['nullable', 'in:yes,no'],
            'recentVigorousExercise' => ['nullable', 'in:yes,no'],
            'psaEligibility' => ['nullable', 'in:Eligible,Deferred'],
            'recallDate' => ['nullable', 'date'],
            'recommendedAction' => ['nullable', 'string', 'max:1000'],

            // Procedures
            'biopsyDone' => ['nullable', 'boolean'],
            'gleasonScore' => ['nullable', 'required_if:biopsyDone,1,true', 'string', 'max:50'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'screeningDate.required' => 'Screening date is required.',
            'screeningResult.required' => 'Screening result is required.',

            'psaLevel.numeric' => 'PSA level must be a number.',
            'psaLevel.min' => 'PSA level cannot be negative.',
            'psaLevel.max' => 'PSA level value is too high.',

            'dreResult.in' => 'DRE result must be positive or negative.',

            'ipssScore.integer' => 'IPSS score must be a whole number.',
            'ipssScore.min' => 'IPSS score cannot be less than 0.',
            'ipssScore.max' => 'IPSS score cannot exceed 35.',

            'gleasonScore.required_if' => 'Gleason score is required when biopsy is done.',

            'treatmentReferral.in' => 'Referral status must be referred or not_referred.',

            'poorUrinaryStream.in' => 'Please select yes or no for poor urinary stream.',
            'urgeIncontinence.in' => 'Please select yes or no for urge incontinence.',
            'delayStartingUrination.in' => 'Please select yes or no for delay starting urination.',
            'inabilityToHoldUrine.in' => 'Please select yes or no for inability to hold urine.',
            'terminalDribbling.in' => 'Please select yes or no for terminal dribbling.',
            'frequentDayUrination.in' => 'Please select yes or no for frequent day urination.',
            'nocturia.in' => 'Please select yes or no for nocturia.',
            'incompleteEmptying.in' => 'Please select yes or no for incomplete emptying.',
            'bloodInUrine.in' => 'Please select yes or no for blood in urine.',
            'inabilityToPassUrine.in' => 'Please select yes or no for inability to pass urine.',
            'bonePainHipBack.in' => 'Please select yes or no for persistent bone/hip/back pain.',
            'unexplainedWeightLoss.in' => 'Please select yes or no for unexplained weight loss.',

            'recentDre.in' => 'Please select yes or no for recent DRE / prostate massage.',
            'recentEjaculation.in' => 'Please select yes or no for recent ejaculation.',
            'recentUrinaryInfection.in' => 'Please select yes or no for recent urinary infection / catheterisation.',
            'recentVigorousExercise.in' => 'Please select yes or no for recent vigorous exercise / cycling.',
        ];
    }
}