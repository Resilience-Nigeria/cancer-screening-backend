<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreeningVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'treatmentReferral' => match ($this->treatmentReferral) {
                'referred' => true,
                'not_referred' => false,
                default => null,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'visitDate' => ['required', 'date'],
            'visitType' => ['required', 'in:initial,follow_up'],
            'notes' => ['nullable', 'string'],
            'treatmentReferral' => ['nullable', 'boolean'],
        ];
    }
}