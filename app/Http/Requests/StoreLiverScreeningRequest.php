<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiverScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hbvStatus' => ['required', 'in:positive,negative'],
            'hcvStatus' => ['required', 'in:positive,negative'],
            'method' => ['required', 'in:uss,afp'],
            'afpValue' => ['nullable', 'numeric', 'min:0'],
            'lesionDetected' => ['required', 'boolean'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
        ];
    }
}