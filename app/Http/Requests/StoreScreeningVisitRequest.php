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
            'visitDate' => ['nullable', 'date'],
            'visitType' => ['required', 'in:initial,follow_up'],
            'notes' => ['nullable', 'string'],
            'treatmentReferral' => ['nullable', 'boolean'],
            'reasonForVisit' => ['nullable', 'in:routine_screening,referral,abnormality_detected'],
'referredIn' => ['nullable', 'in:yes,no'],
'referralNumber' => ['nullable', 'string', 'max:100'],
'entryPoint' => ['nullable', 'in:art_clinic,fp_clinic,anc,l_and_d,other'],
'entryPointOther' => ['nullable', 'string', 'max:255'],
        ];
    }
}