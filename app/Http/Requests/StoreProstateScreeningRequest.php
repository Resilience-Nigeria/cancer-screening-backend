<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProstateScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Screening Details
            'psaLevel' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'dreResult' => ['nullable', 'in:normal,abnormal,suspicious,positive,negative'],
            'ipssScore' => ['nullable', 'integer', 'min:0', 'max:35'],
            'referral' => ['nullable', 'in:referred,not_referred'],
            
            // Urinary Symptoms (9 symptoms as per frontend)
            'poorUrinaryStream' => ['nullable', 'in:yes,no'],
            'urgeIncontinence' => ['nullable', 'in:yes,no'],
            'delayStartingUrination' => ['nullable', 'in:yes,no'],
            'inabilityToHoldUrine' => ['nullable', 'in:yes,no'],
            'terminalDribbling' => ['nullable', 'in:yes,no'],
            'frequentDayUrination' => ['nullable', 'in:yes,no'],
            'nocturia' => ['nullable', 'in:yes,no'],
            'incompleteEmptying' => ['nullable', 'in:yes,no'],
            'bloodInUrine' => ['nullable', 'in:yes,no'],
            
            // Additional Procedures
            'biopsyDone' => ['required', 'boolean'],
            'gleasonScore' => ['nullable', 'required_if:biopsyDone,true', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'psaLevel.numeric' => 'PSA level must be a number.',
            'psaLevel.min' => 'PSA level cannot be negative.',
            'psaLevel.max' => 'PSA level value is too high.',
            
            'dreResult.in' => 'DRE result must be normal, abnormal, or suspicious.',
            
            'ipssScore.integer' => 'IPSS score must be a whole number.',
            'ipssScore.min' => 'IPSS score cannot be less than 0.',
            'ipssScore.max' => 'IPSS score cannot exceed 35.',
            
            'gleasonScore.required_if' => 'Gleason score is required when biopsy is done.',
            
            'referral.in' => 'Referral status must be referred or not_referred.',
            
            // Urinary symptoms validation messages
            'poorUrinaryStream.in' => 'Please select yes or no for poor urinary stream.',
            'urgeIncontinence.in' => 'Please select yes or no for urge incontinence.',
            'delayStartingUrination.in' => 'Please select yes or no for delay starting urination.',
            'inabilityToHoldUrine.in' => 'Please select yes or no for inability to hold urine.',
            'terminalDribbling.in' => 'Please select yes or no for terminal dribbling.',
            'frequentDayUrination.in' => 'Please select yes or no for frequent day urination.',
            'nocturia.in' => 'Please select yes or no for nocturia.',
            'incompleteEmptying.in' => 'Please select yes or no for incomplete emptying.',
            'bloodInUrine.in' => 'Please select yes or no for blood in urine.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert checkbox boolean values
        if ($this->has('biopsyDone')) {
            $biopsyDone = $this->input('biopsyDone');
            if (is_string($biopsyDone)) {
                $this->merge([
                    'biopsyDone' => filter_var($biopsyDone, FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }
    }
}