<?php

namespace App\Services;

use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;

/**
 * Builds the foods of a single meal so its macros match a target.
 *
 * Picks one protein + one carb + one fat food at random and scales their grams
 * until the macro error is within tolerance (retrying with a looser tolerance,
 * then keeping the closest mix). Extracted from GenerateDietJob so it can be
 * reused by the on-demand "otra opción" endpoint.
 *
 * Fixed items (e.g. a protein-powder supplement) are saved as-is and their
 * macros are subtracted from the target before matching, so the rest of the
 * plate squares up around them.
 */
class MealPlateGenerator
{
    /**
     * Populate $meal with meal items matching $targetMacros.
     *
     * @param array{calorias?: float, proteina: float, carbos: float, grasa: float} $targetMacros
     * @param array<int, array{food: Food, grams: float}> $fixedItems
     * @param array<int, string> $excludeFoodIds  foods kept out of the random pool (e.g. supplement foods, added only via injection)
     */
    public function build(Meal $meal, array $targetMacros, User $user, array $fixedItems = [], array $excludeFoodIds = [], float $tolerance = 0.10): void
    {
        // Save fixed items first and subtract their contribution from the target.
        foreach ($fixedItems as $fixed) {
            $this->saveMealItem($meal, $fixed['food'], $fixed['grams']);
            $targetMacros['proteina'] = max(0, $targetMacros['proteina'] - ($fixed['food']->proteina * $fixed['grams'] / 100));
            $targetMacros['carbos']   = max(0, $targetMacros['carbos']   - ($fixed['food']->carbos   * $fixed['grams'] / 100));
            $targetMacros['grasa']    = max(0, $targetMacros['grasa']    - ($fixed['food']->grasa    * $fixed['grams'] / 100));
        }

        $this->fillRemaining($meal, $targetMacros, $user, $excludeFoodIds, $tolerance);
    }

    /**
     * Run the random-mix matching loop for the (already supplement-adjusted) target.
     */
    private function fillRemaining(Meal $meal, array $targetMacros, User $user, array $excludeFoodIds, float $tolerance): void
    {
        // Fetch allergies or intolerances to exclude, plus any explicitly excluded
        // foods (supplement foods only belong in a meal via deliberate injection).
        $restrictedFoodIds = $user->foodRestrictions
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->merge($excludeFoodIds)
            ->unique()
            ->toArray();

        $proteins = Food::where('categoria', 'proteina')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        $carbs = Food::where('categoria', 'carbos')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        $fats = Food::where('categoria', 'grasas')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        // Fallbacks
        if ($proteins->isEmpty()) $proteins = Food::where('categoria', 'proteina')->get();
        if ($carbs->isEmpty()) $carbs = Food::where('categoria', 'carbos')->get();
        if ($fats->isEmpty()) $fats = Food::where('categoria', 'grasas')->get();

        if ($proteins->isEmpty() || $carbs->isEmpty() || $fats->isEmpty()) {
            throw new \Exception("Alimentos insuficientes en BD para generar el plan");
        }

        $attempts = 0;
        $maxAttempts = 50;
        $bestMix = null;
        $bestDiff = 999999;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $pFood = $proteins->random();
            $cFood = $carbs->random();
            $fFood = $fats->random();

            $gP = ($targetMacros['proteina'] / max(0.1, $pFood->proteina)) * 100;
            $gC = ($targetMacros['carbos'] / max(0.1, $cFood->carbos)) * 100;
            $gF = ($targetMacros['grasa'] / max(0.1, $fFood->grasa)) * 100;

            $gP = max(10, min(500, $gP));
            $gC = max(10, min(500, $gC));
            $gF = max(5, min(200, $gF));

            $actualProt = ($pFood->proteina * $gP / 100) + ($cFood->proteina * $gC / 100) + ($fFood->proteina * $gF / 100);
            $actualCarb = ($pFood->carbos * $gP / 100) + ($cFood->carbos * $gC / 100) + ($fFood->carbos * $gF / 100);
            $actualFat  = ($pFood->grasa * $gP / 100) + ($cFood->grasa * $gC / 100) + ($fFood->grasa * $gF / 100);

            $errProt = abs($actualProt - $targetMacros['proteina']) / max(1, $targetMacros['proteina']);
            $errCarb = abs($actualCarb - $targetMacros['carbos']) / max(1, $targetMacros['carbos']);
            $errFat  = abs($actualFat - $targetMacros['grasa']) / max(1, $targetMacros['grasa']);

            $maxError = max($errProt, $errCarb, $errFat);

            if ($maxError <= $tolerance) {
                $this->saveMealItem($meal, $pFood, $gP);
                $this->saveMealItem($meal, $cFood, $gC);
                $this->saveMealItem($meal, $fFood, $gF);
                return;
            }

            if ($maxError < $bestDiff) {
                $bestDiff = $maxError;
                $bestMix = [
                    'foods' => [$pFood, $cFood, $fFood],
                    'grams' => [$gP, $gC, $gF]
                ];
            }
        }

        // If tolerance is 10% and failed, retry with 15%
        if ($tolerance < 0.15) {
            $this->fillRemaining($meal, $targetMacros, $user, $excludeFoodIds, 0.15);
            return;
        }

        if ($bestMix) {
            $this->saveMealItem($meal, $bestMix['foods'][0], $bestMix['grams'][0]);
            $this->saveMealItem($meal, $bestMix['foods'][1], $bestMix['grams'][1]);
            $this->saveMealItem($meal, $bestMix['foods'][2], $bestMix['grams'][2]);
            return;
        }

        throw new \Exception("No se pudo cuadrar los macros de la comida.");
    }

    private function saveMealItem(Meal $meal, Food $food, float $grams): MealItem
    {
        return $meal->mealItems()->create([
            'food_id'         => $food->id,
            'cantidad_gramos' => round($grams, 1),
            'calorias'        => round($food->calorias * $grams / 100, 1),
            'proteina'        => round($food->proteina * $grams / 100, 1),
            'carbos'          => round($food->carbos * $grams / 100, 1),
            'grasa'           => round($food->grasa * $grams / 100, 1),
        ]);
    }
}
