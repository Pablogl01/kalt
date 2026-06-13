<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'num_comidas',
        'es_personalizado',
    ];

    protected function casts(): array
    {
        return [
            'es_personalizado' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealSlots(): HasMany
    {
        return $this->hasMany(MealSlot::class);
    }
}
