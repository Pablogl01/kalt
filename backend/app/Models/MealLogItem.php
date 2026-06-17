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

    protected static function booted(): void
    {
        static::saved(function ($item) {
            if ($item->mealLog && $item->mealLog->dailyLog) {
                \App\Services\StatsService::clearCache($item->mealLog->dailyLog->user_id);
            }
        });

        static::deleted(function ($item) {
            if ($item->mealLog && $item->mealLog->dailyLog) {
                \App\Services\StatsService::clearCache($item->mealLog->dailyLog->user_id);
            }
        });
    }

    public function mealLog(): BelongsTo
    {
        return $this->belongsTo(MealLog::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
