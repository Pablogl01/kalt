<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSupplement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'food_id',
        'dosis_gramos',
        'momento',
        'afecta_macros',
    ];

    protected function casts(): array
    {
        return [
            'afecta_macros' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
