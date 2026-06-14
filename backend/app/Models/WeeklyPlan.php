<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WeeklyPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'semana_inicio',
        'generado_en',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'semana_inicio' => 'date',
            'generado_en'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dayPlans(): HasMany
    {
        return $this->hasMany(DayPlan::class);
    }

    public function shoppingList(): HasOne
    {
        return $this->hasOne(ShoppingList::class);
    }
}
