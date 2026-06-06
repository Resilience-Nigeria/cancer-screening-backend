<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreastScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Fold result -> screeningResult (the NOT NULL column).
        $merge = [
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ];

        // UI value "ultrasound" maps to the stored enum value "uss".
        if ($this->input('method') === 'ultrasound') {
            $merge['method'] = 'uss';
        }

        foreach (['biopsyDone', 'referralCompleted'] as $bool) {
            if ($this->has($bool) && is_string($this->input($bool))) {
                $merge[$bool] = filter_var($this->input($bool), FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['nullable', 'string'],
            'method' => ['required', 'in:cbe,mammography,uss'],
            'screeningDate' => ['required', 'date'],
            'screeningResult' => ['required', 'in:negative,positive,suspicious'],

            // Imaging findings
            'biradsScore' => ['nullable', 'string', 'max:50'],
            'breastDensity' => ['nullable', 'string', 'max:100'],

            // Breast health history & symptoms
            'breastfeedingHistory' => ['nullable', 'in:yes,no'],
            'breastfeedingDuration' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'breastLumps' => ['nullable', 'in:current,previous,none'],
            'breastNippleDischarge' => ['nullable', 'in:yes,no'],
            'dischargeType' => ['nullable', 'required_if:breastNippleDischarge,yes', 'in:bloody,clear,milky,purulent'],
            'skinChanges' => ['nullable', 'in:yes,no'],
            'breastPain' => ['nullable', 'in:yes,no'],
            'previousBreastSurgery' => ['nullable', 'in:yes,no'],
            'previousBiopsy' => ['nullable', 'in:yes,no'],
            'ageAtFirstMenstruation' => ['nullable', 'integer', 'min:0', 'max:30'],
            'ageAtMenopause' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Procedures & follow-up
            'biopsyDone' => ['nullable', 'boolean'],
            'biopsyResult' => ['nullable', 'required_if:biopsyDone,1,true', 'in:positive,negative'],
            'referralCompleted' => ['nullable', 'boolean'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}