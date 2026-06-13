<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shopping_list_id',
        'food_id',
        'cantidad_total',
        'categoria',
        'tengo_en_casa',
        'no_lo_quiero',
        'sustituido_por_food_id',
    ];

    protected function casts(): array
    {
        return [
            'tengo_en_casa' => 'boolean',
            'no_lo_quiero'  => 'boolean',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function substituteFood(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'sustituido_por_food_id');
    }
}
