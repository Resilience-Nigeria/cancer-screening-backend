<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated in the controller via authorizeVisit()
    }

    protected function prepareForValidation(): void
    {
        foreach (['pallor', 'weightLossNoted', 'enlargedLymphNodes', 'jaundice'] as $bool) {
            if ($this->has($bool)) {
                $this->merge([$bool => filter_var($this->input($bool), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'heightCm' => ['nullable', 'numeric', 'min:0'],
            'weightKg' => ['nullable', 'numeric', 'min:0'],
            'bmi' => ['nullable', 'numeric', 'min:0'],
            'bloodPressureSystolic' => ['nullable', 'integer', 'min:0', 'max:300'],
            'bloodPressureDiastolic' => ['nullable', 'integer', 'min:0', 'max:200'],
            'pulse' => ['nullable', 'integer', 'min:0', 'max:300'],
            'temperatureCelsius' => ['nullable', 'numeric', 'min:25', 'max:45'],

            'pallor' => ['nullable', 'boolean'],
            'weightLossNoted' => ['nullable', 'boolean'],
            'enlargedLymphNodes' => ['nullable', 'boolean'],
            'enlargedLymphNodesSite' => ['nullable', 'string', 'max:255'],
            'jaundice' => ['nullable', 'boolean'],

            'notes' => ['nullable', 'string'],
        ];
    }
}
