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
            'familyHistory' => ['required', 'in:yes,no'],
            'smokingStatus' => ['nullable', 'in:active_smoker,passive_smoker,ocassional'],
            'alcoholConsumption' => ['nullable', 'in:none,occasional,regular'],
            'weightKg' => ['nullable', 'numeric', 'min:0'],
            'heightCm' => ['nullable', 'numeric', 'min:0'],
            'hivStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hbvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'hcvStatus' => ['nullable', 'in:positive,negative,unknown'],
            'comorbiditiesJson' => ['nullable', 'array'],
            'comorbiditiesJson.*' => ['string'],
        ];
    }
}