<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCervicalScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `screeningResult` is the NOT NULL column. Callers may send `result`
     * (modals) or `screeningResult` (wizard) — fold either into screeningResult
     * so `result` never reaches the model (no such column).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ]);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['nullable', 'string'],
            'method' => ['required', 'in:via,pap,hpv'],
            'screeningDate' => ['required', 'date'],
            'screeningResult' => ['required', 'in:negative,positive,suspicious,non_suspicious'],

            'hpvResult' => ['nullable', 'string', 'max:255'],
            'hpvGenotype' => ['nullable', 'string', 'max:255'],

            'colposcopyDone' => ['nullable', 'boolean'],
            'biopsyDone' => ['nullable', 'boolean'],
            'biopsyResult' => ['nullable', 'required_if:biopsyDone,1,true', 'in:positive,negative'],

            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],

            // Cervical-specific risk factors
            'moreThanOnePartner' => ['nullable', 'in:yes,no'],
            'ageAtFirstIntercourse' => ['nullable', 'integer', 'min:0', 'max:100'],
            'numberOfChildbirths' => ['nullable', 'integer', 'min:0', 'max:50'],
            'contraceptiveUse' => ['nullable', 'in:none,oral_contraceptives,iud,barrier_methods,other'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}