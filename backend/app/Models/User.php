<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'sexo',
        'peso',
        'altura',
        'edad',
        'grasa_corporal',
        'objetivo',
        'nivel_actividad',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            // Health data — encrypted at application level (RGPD/LOPD)
            'peso'              => 'encrypted',
            'grasa_corporal'    => 'encrypted',
        ];
    }

    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyPlan::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailyLog::class);
    }

    public function foodRestrictions(): HasMany
    {
        return $this->hasMany(UserFoodRestriction::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(UserWeightLog::class);
    }

    public function mealTemplates(): HasMany
    {
        return $this->hasMany(MealTemplate::class);
    }
}
