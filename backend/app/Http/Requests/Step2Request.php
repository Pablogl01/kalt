<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nivel_actividad' => ['required', Rule::in(['sedentario', 'ligero', 'moderado', 'alto'])],
            'dias_entreno'    => ['required', 'array'],
            'dias_entreno.*'  => ['integer', 'min:1', 'max:7'],
            'num_comidas'     => ['required', 'integer', 'min:2', 'max:7'],
            'hora_gimnasio'   => ['nullable', 'string'],
        ];
    }
}
