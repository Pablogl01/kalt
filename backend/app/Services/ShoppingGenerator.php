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
            // Conserva las marcas manuales del usuario por food_id antes de
            // recrear la lista, para no destruir su trabajo al regenerar.
            $previousMarks = [];
            $existing = $weeklyPlan->shoppingList;
            if ($existing) {
                foreach ($existing->shoppingItems as $item) {
                    $previousMarks[$item->food_id] = [
                        'tengo_en_casa'          => $item->tengo_en_casa,
                        'no_lo_quiero'           => $item->no_lo_quiero,
                        'sustituido_por_food_id' => $item->sustituido_por_food_id,
                    ];
                }
            }

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
                $marks = $previousMarks[$foodId] ?? null;
                $shoppingList->shoppingItems()->create([
                    'food_id' => $foodId,
                    'cantidad_total' => $items->sum('cantidad_gramos'),
                    'categoria' => $food->categoria ?? 'otros',
                    'tengo_en_casa'          => $marks['tengo_en_casa'] ?? false,
                    'no_lo_quiero'           => $marks['no_lo_quiero'] ?? false,
                    'sustituido_por_food_id' => $marks['sustituido_por_food_id'] ?? null,
                ]);
            }

            // Suplementos que no son meal items (p.ej. creatina): se añaden con la
            // dosis semanal. La whey ya entra arriba por ser un meal item.
            $user = $weeklyPlan->user;
            if ($user) {
                foreach ($user->supplements()->with('food')->get() as $supp) {
                    if (!$supp->food || $grouped->has($supp->food_id)) {
                        continue;
                    }
                    $marks = $previousMarks[$supp->food_id] ?? null;
                    $shoppingList->shoppingItems()->create([
                        'food_id' => $supp->food_id,
                        'cantidad_total' => round($supp->dosis_gramos * 7, 2),
                        'categoria' => $supp->food->categoria ?? 'suplemento',
                        'tengo_en_casa'          => $marks['tengo_en_casa'] ?? false,
                        'no_lo_quiero'           => $marks['no_lo_quiero'] ?? false,
                        'sustituido_por_food_id' => $marks['sustituido_por_food_id'] ?? null,
                    ]);
                }
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
