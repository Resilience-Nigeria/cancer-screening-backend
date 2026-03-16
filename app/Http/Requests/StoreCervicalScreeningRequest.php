<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCervicalScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:via,pap,hpv'],
            'screeningDate' => ['required', 'date'],
            'result' => ['required', 'in:negative,positive,suspicious'],
            'hpvResult' => ['nullable', 'string', 'max:255'],
            'hpvGenotype' => ['nullable', 'string', 'max:255'],
            'colposcopyDone' => ['required', 'boolean'],
            'biopsyDone' => ['required', 'boolean'],
            'biopsyResult' => ['nullable', 'required_if:biopsyDone,1', 'in:positive,negative'],
            'treatmentProvided' => ['required', 'boolean'],
            'referralCompleted' => ['required', 'boolean'],
        ];
    }
}