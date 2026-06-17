<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWeightLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'fecha',
        'peso',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            // Health data encrypted at rest (RGPD)
            'peso'  => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function ($weightLog) {
            \App\Services\StatsService::clearCache($weightLog->user_id);
        });

        static::deleted(function ($weightLog) {
            \App\Services\StatsService::clearCache($weightLog->user_id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
