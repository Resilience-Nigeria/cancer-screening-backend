<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRiskProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Numeric fields sent as an empty string (very common from a number
     * input left blank, e.g. age at menopause for a client who is male
     * or hasn't reached menopause) need to become null before
     * validation - Laravel's `nullable` rule only skips validation for
     * an actual null value, not an empty string, so "" still fails an
     * `integer`/`numeric` check even with `nullable` present.
     */
    protected function prepareForValidation(): void
    {
        $numericFields = [
            'ageAtFirstMenstruation',
            'ageAtMenopause',
            'weightKg',
            'heightCm',
            'breastfeedingDuration',
        ];

        $normalized = [];
        foreach ($numericFields as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'familyHistory' => ['required', 'in:yes,no,unknown'],
            'smokingStatus' => ['nullable', 'in:non_smoker,never,active_smoker,current_smoker,passive_smoker,ocassional,occasional,former_smoker,ex_smoker'],
            'alcoholConsumption' => ['nullable', 'in:none,never,non_drinker,occasional,occasionally,light_drinker,regular,regularly,weekly,daily,heavy_drinker'],
            'physicalActivityLevel' => ['nullable', 'in:regular,sometimes,rarely'],
            'occupationCategory' => ['nullable', 'string', 'max:10'],
            'weightKg' => ['nullable', 'numeric', 'min:0'],
            'heightCm' => ['nullable', 'numeric', 'min:0'],
            'hivStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hbvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hcvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'comorbiditiesJson' => ['nullable', 'array'],
            'comorbiditiesJson.*' => ['string'],
            'ageAtFirstMenstruation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ageAtMenopause' => ['nullable', 'integer', 'min:0', 'max:100'],
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
