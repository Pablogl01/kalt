<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'nombre',
        'categoria',
        'calorias',
        'proteina',
        'carbos',
        'grasa',
        'apto_volumen',
        'apto_definicion',
        'apto_mantenimiento',
    ];

    protected function casts(): array
    {
        return [
            'apto_volumen'       => 'boolean',
            'apto_definicion'    => 'boolean',
            'apto_mantenimiento' => 'boolean',
        ];
    }

    public function substitutes(): HasMany
    {
        return $this->hasMany(FoodSubstitute::class);
    }
}
