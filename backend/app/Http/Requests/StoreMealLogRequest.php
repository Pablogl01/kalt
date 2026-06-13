<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meal_id'   => ['required', 'uuid', 'exists:meals,id'],
            'realizada' => ['required', 'boolean'],
            'es_extra'  => ['boolean'],
            'hora_real' => ['nullable', 'date_format:H:i'],
            'items'     => ['sometimes', 'array'],
            'items.*.food_id'          => ['required_with:items', 'uuid', 'exists:foods,id'],
            'items.*.cantidad_gramos'  => ['required_with:items', 'numeric', 'min:1'],
        ];
    }
}
