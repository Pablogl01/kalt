<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealLogItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'meal_log_id',
        'food_id',
        'cantidad_gramos',
        'calorias',
        'proteina',
        'carbos',
        'grasa',
    ];

    public function mealLog(): BelongsTo
    {
        return $this->belongsTo(MealLog::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
