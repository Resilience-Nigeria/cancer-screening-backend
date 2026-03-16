<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreColorectalScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:fit,fobt,colonoscopy'],
            'screeningDate' => ['required', 'date'],
            'result' => ['required', 'in:negative,positive,suspicious'],
            'polypDetected' => ['required', 'boolean'],
            'histologyResult' => ['nullable', 'in:negative,positive'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
        ];
    }
}