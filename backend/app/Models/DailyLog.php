<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'fecha',
        'entreno_planificado',
        'ha_entrenado',
        'hora_gimnasio',
        'tipo_sesion',
        'recalculo_motivo',
        'nota_exceso',
    ];

    protected function casts(): array
    {
        return [
            'fecha'               => 'date',
            'entreno_planificado' => 'boolean',
            'ha_entrenado'        => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealLogs(): HasMany
    {
        return $this->hasMany(MealLog::class);
    }
}
