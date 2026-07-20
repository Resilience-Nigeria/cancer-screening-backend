<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRiskProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'familyHistory' => ['required', 'in:yes,no,unknown'],
            'smokingStatus' => ['nullable', 'in:non_smoker,active_smoker,passive_smoker,ocassional,former_smoker'],
            'alcoholConsumption' => ['nullable', 'in:none,occasionally,regularly,weekly,daily,never'],
            'weightKg' => ['nullable', 'numeric', 'min:0'],
            'heightCm' => ['nullable', 'numeric', 'min:0'],
            'hivStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hbvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hcvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'comorbiditiesJson' => ['nullable', 'array'],
            'comorbiditiesJson.*' => ['string'],
            'ageAtFirstMenstruation' => ['integer', 'min:0', 'max:100'],
            'ageAtMenopause' => ['integer', 'min:0', 'max:100'],
            'breastfeedingHistory' => ['nullable', 'in:yes,no'],
            'breastfeedingDuration' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'previousBreastSurgery' => ['nullable', 'in:yes,no'],

            // Stage 2, Section C — medical history confirm checklist
            'previousCancer' => ['nullable', 'in:yes,no,unknown'],
            'previousCancerDetails' => ['nullable', 'string', 'max:255'],
            'previousSurgeries' => ['nullable', 'in:yes,no,unknown'],
            'previousSurgeriesDetails' => ['nullable', 'string', 'max:255'],
            'diabetes' => ['nullable', 'in:yes,no,unknown'],
            'hypertension' => ['nullable', 'in:yes,no,unknown'],
            'previousScreening' => ['nullable', 'in:yes,no,unknown'],
            'previousScreeningDetails' => ['nullable', 'string', 'max:255'],
        ];
    }
}