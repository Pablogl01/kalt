<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'daily_log_id',
        'meal_id',
        'es_extra',
        'realizada',
        'saltada',
        'hora_real',
    ];

    protected function casts(): array
    {
        return [
            'es_extra'  => 'boolean',
            'realizada' => 'boolean',
            'saltada'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function ($mealLog) {
            if ($mealLog->dailyLog) {
                \App\Services\StatsService::clearCache($mealLog->dailyLog->user_id);
            }
        });

        static::deleted(function ($mealLog) {
            if ($mealLog->dailyLog) {
                \App\Services\StatsService::clearCache($mealLog->dailyLog->user_id);
            }
        });
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function mealLogItems(): HasMany
    {
        return $this->hasMany(MealLogItem::class);
    }
}
