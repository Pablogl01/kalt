<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealSlot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'meal_template_id',
        'orden',
        'nombre',
        'hora_objetivo',
        'es_pre_entreno',
        'es_post_entreno',
    ];

    protected function casts(): array
    {
        return [
            'es_pre_entreno'  => 'boolean',
            'es_post_entreno' => 'boolean',
        ];
    }

    public function mealTemplate(): BelongsTo
    {
        return $this->belongsTo(MealTemplate::class);
    }
}
