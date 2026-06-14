<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step3Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restricciones'            => ['nullable', 'array'],
            'restricciones.*.food_id'  => ['required_with:restricciones', 'uuid', 'exists:foods,id'],
            'restricciones.*.tipo'     => ['required_with:restricciones', Rule::in(['alergia', 'intolerancia', 'no_me_gusta'])],
        ];
    }
}
