<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiverScreeningRequest extends FormRequest
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

        if ($this->has('lesionDetected') && is_string($this->input('lesionDetected'))) {
            $merge['lesionDetected'] = filter_var($this->input('lesionDetected'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['nullable', 'string'],
            'hbvStatus' => ['required', 'in:positive,negative'],
            'hcvStatus' => ['required', 'in:positive,negative'],
            'method' => ['required', 'in:uss,afp'],
            'screeningDate' => ['required', 'date'],
            'screeningResult' => ['required', 'in:negative,positive,suspicious'],
            // Only sent when method = afp
            'afpValue' => ['nullable', 'numeric', 'min:0'],
            // Only sent when method = uss; defaults false in the DB
            'lesionDetected' => ['nullable', 'boolean'],
            'treatmentReferral' => ['nullable', 'in:referred,not_referred'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}