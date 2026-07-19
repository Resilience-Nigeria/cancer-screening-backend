<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'dateOfBirth' => ['required', 'date', 'before_or_equal:today'],
            'phoneNumber' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'nin' => ['nullable', 'string', 'max:30'],
            'screeningCategory' => ['required', 'in:new_client,follow_up'],
            'stateOfOrigin' => ['required', 'string', 'max:255'],
            'lgaOfOrigin' => ['required', 'string', 'max:255'],
            'stateOfResidence' => ['required', 'string', 'max:255'],
            'lgaOfResidence' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:300'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'nextOfKinName' => ['nullable', 'string', 'max:255'],
            'nextOfKinPhone' => ['nullable', 'string', 'max:30'],
            'nextOfKinRelationship' => ['nullable', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:300'],
            'registrationDate' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email address field is required.',
            'email.email' => 'Please enter a valid email address.',
            'stateOfOrigin.required' => 'The state of origin field is required.',
            'lgaOfOrigin.required' => 'The LGA of origin field is required.',
            'stateOfResidence.required' => 'The state of residence field is required.',
            'lgaOfResidence.required' => 'The LGA of residence field is required.',
        ];
    }
}