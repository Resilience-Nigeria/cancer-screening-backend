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
            'psaLevel' => ['nullable', 'numeric', 'min:0'],
            'dreResult' => ['nullable', 'in:positive,negative'],
            'ipssScore' => ['nullable', 'integer', 'min:0', 'max:35'],
            'biopsyDone' => ['required', 'boolean'],
            'gleasonScore' => ['nullable', 'required_if:biopsyDone,1', 'string', 'max:50'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
        ];
    }
}