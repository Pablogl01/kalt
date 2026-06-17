<?php

namespace App\Services;

use App\Models\MealItem;
use App\Models\ShoppingList;
use App\Models\WeeklyPlan;
use Illuminate\Support\Facades\DB;

class ShoppingGenerator
{
    /**
     * Genera la lista de la compra a partir del plan activo.
     */
    public function generate(WeeklyPlan $weeklyPlan): ShoppingList
    {
        return DB::transaction(function () use ($weeklyPlan) {
            // Borra la lista existente de este plan para evitar duplicados
            $weeklyPlan->shoppingList()->delete();

            $shoppingList = ShoppingList::create([
                'weekly_plan_id' => $weeklyPlan->id,
                'generada_en' => now(),
            ]);

            // Obtener todos los meal items del plan semanal
            $mealItems = MealItem::whereHas('meal.dayPlan', function ($query) use ($weeklyPlan) {
                $query->where('weekly_plan_id', $weeklyPlan->id);
            })->with('food')->get();

            // Agrupar por food_id
            $grouped = $mealItems->groupBy('food_id');

            foreach ($grouped as $foodId => $items) {
                $food = $items->first()->food;
                $shoppingList->shoppingItems()->create([
                    'food_id' => $foodId,
                    'cantidad_total' => $items->sum('cantidad_gramos'),
                    'categoria' => $food->categoria ?? 'otros',
                    'tengo_en_casa' => false,
                    'no_lo_quiero' => false,
                ]);
            }

            return $shoppingList;
        });
    }

    /**
     * Alias semántico de generate() para regeneración explícita.
     */
    public function regenerate(WeeklyPlan $weeklyPlan): ShoppingList
    {
        return $this->generate($weeklyPlan);
    }
}
