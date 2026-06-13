<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'email'             => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'sexo'              => ['sometimes', 'required', Rule::in(['hombre', 'mujer'])],
            'peso'              => ['sometimes', 'required', 'numeric', 'min:20', 'max:500'],
            'altura'            => ['sometimes', 'required', 'numeric', 'min:100', 'max:250'],
            'edad'              => ['sometimes', 'required', 'integer', 'min:10', 'max:120'],
            'grasa_corporal'    => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:60'],
            'objetivo'          => ['sometimes', 'required', Rule::in(['volumen', 'mantenimiento', 'definicion'])],
            'nivel_actividad'   => ['sometimes', 'required', Rule::in(['sedentario', 'ligero', 'moderado', 'alto'])],
        ];
    }
}
