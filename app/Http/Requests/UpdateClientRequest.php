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
            'gender' => ['sometimes', 'required', 'in:male,female'],
            'dateOfBirth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'screeningCategory' => ['sometimes', 'required', 'in:new_client,follow_up'],
            'state' => ['nullable', 'string', 'max:255'],
            'lga' => ['nullable', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
            'registrationDate' => ['sometimes', 'required', 'date'],
        ];
    }
}