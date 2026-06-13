<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFoodRestriction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'food_id',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            // tipo contains health data (allergy type) — encrypted at rest
            'tipo' => 'encrypted',
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
