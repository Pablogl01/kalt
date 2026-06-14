<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sexo'     => ['required', Rule::in(['hombre', 'mujer'])],
            'peso'     => ['required', 'numeric', 'min:20', 'max:500'],
            'altura'   => ['required', 'numeric', 'min:100', 'max:250'],
            'edad'     => ['required', 'integer', 'min:10', 'max:120'],
            'objetivo' => ['required', Rule::in(['volumen', 'mantenimiento', 'definicion'])],
        ];
    }
}
