<?php

namespace App\Services;

use App\Models\Food;
use App\Models\FoodSubstitute;
use App\Models\MealItem;
use App\Models\UserFoodRestriction;

/**
 * Finds valid food substitutes respecting user restrictions.
 * IMPORTANT: backend always validates restrictions — never trust frontend selection.
 * Unit tests: tests/Unit/Services/SubstituteEngineTest.php (Phase 4)
 */
class SubstituteEngine
{
    public function getSubstitutes(string $foodId, string $userId): array
    {
        $restrictedFoodIds = UserFoodRestriction::where('user_id', $userId)
            ->get()
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->toArray();

        return FoodSubstitute::where('food_id', $foodId)
            ->whereNotIn('substitute_food_id', $restrictedFoodIds)
            ->orderByDesc('similitud_macros')
            ->limit(3)
            ->with('substituteFood')
            ->get()
            ->map(function ($sub) {
                $food = $sub->substituteFood;
                if ($food) {
                    $food->similitud_macros = $sub->similitud_macros;
                }
                return $food;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function applySubstitute(MealItem $item, string $substituteFoodId, string $userId): MealItem
    {
        $substituteRel = FoodSubstitute::where('food_id', $item->food_id)
            ->where('substitute_food_id', $substituteFoodId)
            ->first();
        
        if (!$substituteRel) {
            throw new \InvalidArgumentException("Invalid substitute food for this item.");
        }

        $restrictedFoodIds = UserFoodRestriction::where('user_id', $userId)
            ->get()
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->toArray();

        if (in_array($substituteFoodId, $restrictedFoodIds)) {
            throw new \InvalidArgumentException("Food is restricted for this user.");
        }

        $substituteFood = Food::findOrFail($substituteFoodId);

        $originalCalories = $item->calorias;
        $substituteCaloriesPer100g = $substituteFood->calorias;

        if ($substituteCaloriesPer100g > 0) {
            $cantidadGramos = ($originalCalories / $substituteCaloriesPer100g) * 100;
        } else {
            $cantidadGramos = $item->cantidad_gramos;
        }

        $item->update([
            'food_id' => $substituteFoodId,
            'cantidad_gramos' => round($cantidadGramos, 2),
            'calorias' => round(($substituteFood->calorias * $cantidadGramos) / 100, 2),
            'proteina' => round(($substituteFood->proteina * $cantidadGramos) / 100, 2),
            'carbos' => round(($substituteFood->carbos * $cantidadGramos) / 100, 2),
            'grasa' => round(($substituteFood->grasa * $cantidadGramos) / 100, 2),
        ]);

        return $item;
    }
}
