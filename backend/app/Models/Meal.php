<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'day_plan_id',
        'meal_slot_id',
        'nombre',
        'hora_objetivo',
    ];

    public function dayPlan(): BelongsTo
    {
        return $this->belongsTo(DayPlan::class);
    }

    public function mealSlot(): BelongsTo
    {
        return $this->belongsTo(MealSlot::class);
    }

    public function mealItems(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }
}
