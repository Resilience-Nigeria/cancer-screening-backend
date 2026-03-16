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
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'screeningCategory' => ['required', 'in:new_client,follow_up'],
            'state' => ['nullable', 'string', 'max:255'],
            'lga' => ['nullable', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
            'registrationDate' => ['required', 'date'],
        ];
    }
}