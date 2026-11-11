<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['phoneNotClients', 'otpSkipped', 'dobEstimated'] as $bool) {
            if ($this->has($bool)) {
                $this->merge([$bool => filter_var($this->input($bool), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        // A typed age is stored as an estimated date of birth (1 January of the birth year).
        $age = $this->input('age');
        if (empty($this->input('dateOfBirth')) && $age !== null && $age !== '' && is_numeric($age)) {
            $age = (int) $age;
            if ($age >= 0 && $age <= 120) {
                $this->merge([
                    'dateOfBirth' => now()->subYears($age)->startOfYear()->toDateString(),
                    'dobEstimated' => true,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'dateOfBirth' => ['required', 'date', 'before_or_equal:today'],
            'dobEstimated' => ['nullable', 'boolean'],
            'offlineId' => ['nullable', 'string', 'max:100'],
            'phoneNumber' => ['required', 'string', 'max:30'],
            'phoneNotClients' => ['nullable', 'boolean'],
            'caregiverName' => ['nullable', 'string', 'max:255'],
            'caregiverPhoneNumber' => ['nullable', 'string', 'max:30'],
            'otpSkipped' => ['nullable', 'boolean'],
            'otpSkipReason' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'nin' => ['nullable', 'string', 'max:30'],
            'hospitalNumber' => ['nullable', 'string', 'max:50'],
            'screeningCategory' => ['required', 'in:new_client,follow_up'],
            'stateOfOrigin' => ['required', 'string', 'max:255'],
            'lgaOfOrigin' => ['required', 'string', 'max:255'],
            'stateOfResidence' => ['required', 'string', 'max:255'],
            'lgaOfResidence' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:300'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'in:islam,christianity,traditional,other'],
            'religionOther' => ['nullable', 'string', 'max:100'],
            'maritalStatus' => ['nullable', 'in:single,married,divorced,separated'],
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
            'dateOfBirth.required' => "Enter a date of birth or the client's age.",
            'email.email' => 'Please enter a valid email address.',
            'stateOfOrigin.required' => 'The state of origin field is required.',
            'lgaOfOrigin.required' => 'The LGA of origin field is required.',
            'stateOfResidence.required' => 'The state of residence field is required.',
            'lgaOfResidence.required' => 'The LGA of residence field is required.',
        ];
    }
}
