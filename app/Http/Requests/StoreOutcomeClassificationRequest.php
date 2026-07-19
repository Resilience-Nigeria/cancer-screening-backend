<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutcomeClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated in the controller via authorizeVisit()
    }

    public function rules(): array
    {
        return [
            'overallOutcome' => ['required', 'in:normal,low_suspicion,suspicious,urgent_referral'],
            'outcomeNotes' => ['nullable', 'string'],
            'repeatScreeningDate' => ['nullable', 'date', 'required_if:overallOutcome,low_suspicion'],
        ];
    }
}
