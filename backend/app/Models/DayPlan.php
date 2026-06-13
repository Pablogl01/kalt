<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'weekly_plan_id',
        'fecha',
        'calorias_objetivo',
        'proteina_obj',
        'carbos_obj',
        'grasa_obj',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}
