<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
     

        return [
            
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'search' => ['required', 'string', 'max:30'],
            // 'screeningCategory' => ['sometimes', 'required', 'in:new_client,follow_up'],
            'clientId' => ['nullable', 'string', 'max:255'],
            
        ];
    }
}