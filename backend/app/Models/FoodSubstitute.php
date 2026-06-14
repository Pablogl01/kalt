<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodSubstitute extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'food_substitutes';

    protected $fillable = [
        'food_id',
        'substitute_food_id',
        'similitud_macros',
    ];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function substituteFood(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'substitute_food_id');
    }
}
