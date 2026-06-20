<?php

namespace App\Http\Controllers;

use App\Jobs\RegenerateShoppingListJob;
use App\Models\Meal;
use App\Services\MealPlateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MealController extends Controller
{
    /**
     * Regenerate the foods of a single meal ("otra opción").
     *
     * Keeps the meal's macro target (the sum of its current items) and rolls a
     * fresh combination of foods that hits it, then refreshes the shopping list.
     */
    public function regenerate(Meal $meal, MealPlateGenerator $plateGenerator): JsonResponse
    {
        $this->authorize('update', $meal);

        $meal->load('mealItems');
        $user = $meal->dayPlan->weeklyPlan->user;

        // Keep any supplement item (e.g. whey) fixed; only reroll the rest, so
        // the target excludes the supplement's macros.
        $supplementFoodIds = $user->supplements()
            ->where('afecta_macros', true)
            ->pluck('food_id')
            ->all();

        $regenerable = $meal->mealItems->whereNotIn('food_id', $supplementFoodIds);

        $target = [
            'proteina' => round($regenerable->sum('proteina'), 1),
            'carbos'   => round($regenerable->sum('carbos'), 1),
            'grasa'    => round($regenerable->sum('grasa'), 1),
        ];

        if (array_sum($target) <= 0) {
            return response()->json(['message' => 'La comida no tiene macros que regenerar.'], 422);
        }

        DB::transaction(function () use ($meal, $target, $user, $plateGenerator, $regenerable, $supplementFoodIds) {
            $meal->mealItems()->whereIn('id', $regenerable->pluck('id'))->delete();
            $plateGenerator->build($meal, $target, $user, [], $supplementFoodIds);
        });

        // Sync the shopping list off the request thread.
        RegenerateShoppingListJob::dispatch($meal->dayPlan->weeklyPlan);

        return response()->json($meal->fresh()->load('mealItems.food'));
    }
}
