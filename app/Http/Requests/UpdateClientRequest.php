<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullName' => ['sometimes', 'required', 'string', 'max:255'],
            'nin' => ['nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', 'in:male,female'],
            'dateOfBirth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'screeningCategory' => ['sometimes', 'required', 'in:new_client,follow_up'],
            'stateOfOrigin' => ['nullable', 'string', 'max:255'],
            'lgaOfOrigin' => ['nullable', 'string', 'max:255'],
            'stateOfResidence' => ['nullable', 'string', 'max:255'],
            'lgaOfResidence' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:300'],
            'landmark' => ['nullable', 'string', 'max:300'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'in:islam,christianity,traditional,other'],
            'religionOther' => ['nullable', 'string', 'max:100'],
            'maritalStatus' => ['nullable', 'in:single,married,divorced,separated'],
            'nextOfKinName' => ['nullable', 'string', 'max:255'],
            'nextOfKinPhone' => ['nullable', 'string', 'max:30'],
            'nextOfKinRelationship' => ['nullable', 'string', 'max:100'],
            'registrationDate' => ['sometimes', 'required', 'date'],
        ];
    }
}