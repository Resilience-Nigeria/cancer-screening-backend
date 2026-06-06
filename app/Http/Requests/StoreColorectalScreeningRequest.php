<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreColorectalScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'screeningResult' => $this->input('screeningResult', $this->input('result')),
        ];

        // The UI sends "histology"; the column is "histologyResult".
        if ($this->has('histology') && !$this->filled('histologyResult')) {
            $merge['histologyResult'] = $this->input('histology');
        }

        if ($this->has('polypDetected') && is_string($this->input('polypDetected'))) {
            $merge['polypDetected'] = filter_var($this->input('polypDetected'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['nullable', 'string'],
            'method' => ['required', 'in:fit,fobt,colonoscopy'],
            'screeningDate' => ['required', 'date'],
            'screeningResult' => ['required', 'in:negative,positive,suspicious'],
            'polypDetected' => ['nullable', 'boolean'],
            'histologyResult' => ['nullable', 'in:negative,positive'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}