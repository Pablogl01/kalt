<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'peso'  => ['required', 'numeric', 'min:20', 'max:500'],
            'fecha' => ['required', 'date'],
            'nota'  => ['nullable', 'string', 'max:100'],
        ];
    }
}
