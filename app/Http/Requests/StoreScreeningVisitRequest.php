<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScreeningVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitDate' => ['required', 'date'],
            'visitType' => ['required', 'in:initial,follow_up'],
            'notes' => ['nullable', 'string'],
        ];
    }
}