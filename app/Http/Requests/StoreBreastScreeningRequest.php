<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreastScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:cbe,mammography,uss'],
            'screeningDate' => ['required', 'date'],
            'biradsScore' => ['nullable', 'string', 'max:50'],
            'breastDensity' => ['nullable', 'string', 'max:100'],
            'biopsyDone' => ['required', 'boolean'],
            'histologyResult' => ['nullable', 'required_if:biopsyDone,1', 'in:negative,positive'],
            'referralOutcome' => ['nullable', 'in:referred,not_referred'],
        ];
    }
}