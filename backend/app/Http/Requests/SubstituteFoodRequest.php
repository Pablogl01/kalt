<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubstituteFoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_id'            => ['required', 'uuid', 'exists:foods,id'],
            'substitute_food_id' => ['required', 'uuid', 'exists:foods,id'],
            'context'            => ['required', 'string', Rule::in(['plan', 'log', 'shopping'])],
        ];
    }
}
