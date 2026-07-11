<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAwarenessRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; } // public form

    public function rules(): array
    {
        return [
            'fullName'         => ['required', 'string', 'max:255'],
            'gender'           => ['required', 'in:male,female'],
            'phoneNumber'      => ['required', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'stateOfResidence' => ['required', 'string', 'max:100'],
            'lgaOfResidence'   => ['required', 'string', 'max:100'],
            'campaignSource'   => ['nullable', 'string', 'max:100'],
            'areaOfResidence' => ['nullable', 'string', 'max:100'],
        ];
    }
}