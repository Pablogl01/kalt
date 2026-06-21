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

        $this->fillRemaining($meal, $targetMacros, $user, $excludeFoodIds);
    }

    /**
     * Pick three foods (protein/carb/fat) and solve for the grams that hit the
     * (already supplement-adjusted) macro target, retrying random combos until
     * one lands in sane portions; keeps the closest as a fallback.
     */
    private function fillRemaining(Meal $meal, array $targetMacros, User $user, array $excludeFoodIds): void
    {
        // Fetch allergies or intolerances to exclude, plus any explicitly excluded
        // foods (supplement foods only belong in a meal via deliberate injection).
        $restrictedFoodIds = $user->foodRestrictions
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->merge($excludeFoodIds)
            ->unique()
            ->toArray();

        // Breakfast/snack slots only draw protein & carbs from breakfast-appropriate
        // foods, so we don't get cod + yuca for breakfast. Fats fit any meal.
        // Breakfast protein also pulls from dairy (yogur, requesón…) for variety.
        $isBreakfast = $this->isBreakfastSlot($meal->nombre);
        $proteinCategories = $isBreakfast ? ['proteina', 'lacteos'] : ['proteina'];

        // Main meals exclude breakfast/snack-only foods (protein powder, oats,
        // rice cakes…); breakfast meals restrict to breakfast-appropriate foods.
        $proteins = Food::whereIn('categoria', $proteinCategories)
            ->whereNotIn('id', $restrictedFoodIds)
            ->when($isBreakfast, fn ($q) => $q->where('apto_desayuno', true))
            ->when(!$isBreakfast, fn ($q) => $q->where('apto_principal', true))
            ->get();

        $carbs = Food::where('categoria', 'carbos')
            ->whereNotIn('id', $restrictedFoodIds)
            ->when($isBreakfast, fn ($q) => $q->where('apto_desayuno', true))
            ->when(!$isBreakfast, fn ($q) => $q->where('apto_principal', true))
            ->get();

        $fats = Food::where('categoria', 'grasas')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        // Fallbacks (drop the breakfast/restriction filters as a last resort)
        if ($proteins->isEmpty()) $proteins = Food::where('categoria', 'proteina')->get();
        if ($carbs->isEmpty()) $carbs = Food::where('categoria', 'carbos')->get();
        if ($fats->isEmpty()) $fats = Food::where('categoria', 'grasas')->get();

        if ($proteins->isEmpty() || $carbs->isEmpty() || $fats->isEmpty()) {
            throw new \Exception("Alimentos insuficientes en BD para generar el plan");
        }

        // Sane gram bounds per food role. The protein cap doubles as a
        // feasibility filter: combos needing >300g of a lean protein are
        // rejected so a denser protein gets picked instead of overshooting.
        $bounds = [[10, 300], [10, 500], [5, 200]]; // protein, carb, fat

        $attempts = 0;
        $maxAttempts = 120;
        $bestMix = null;
        $bestDiff = 999999;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $pFood = $proteins->random();
            $cFood = $carbs->random();
            $fFood = $fats->random();

            // Solve for the grams that hit all three macro targets *exactly*,
            // accounting for every food's contribution to every macro (cross
            // terms), instead of scaling each food by its own macro alone.
            $grams = $this->solveGrams($pFood, $cFood, $fFood, $targetMacros);
            if ($grams === null) {
                continue; // foods macro-collinear: no unique solution
            }

            // Exact solution within sane portions → done, error ≈ 0.
            $inBounds = true;
            foreach ($grams as $i => $g) {
                if ($g < $bounds[$i][0] || $g > $bounds[$i][1]) { $inBounds = false; break; }
            }
            if ($inBounds) {
                $this->saveMealItem($meal, $pFood, $grams[0]);
                $this->saveMealItem($meal, $cFood, $grams[1]);
                $this->saveMealItem($meal, $fFood, $grams[2]);
                return;
            }

            // Otherwise clamp to bounds and keep the closest combo as a fallback.
            $gP = max($bounds[0][0], min($bounds[0][1], $grams[0]));
            $gC = max($bounds[1][0], min($bounds[1][1], $grams[1]));
            $gF = max($bounds[2][0], min($bounds[2][1], $grams[2]));

            $actualProt = ($pFood->proteina * $gP + $cFood->proteina * $gC + $fFood->proteina * $gF) / 100;
            $actualCarb = ($pFood->carbos * $gP + $cFood->carbos * $gC + $fFood->carbos * $gF) / 100;
            $actualFat  = ($pFood->grasa * $gP + $cFood->grasa * $gC + $fFood->grasa * $gF) / 100;

            $maxError = max(
                abs($actualProt - $targetMacros['proteina']) / max(1, $targetMacros['proteina']),
                abs($actualCarb - $targetMacros['carbos'])   / max(1, $targetMacros['carbos']),
                abs($actualFat - $targetMacros['grasa'])     / max(1, $targetMacros['grasa'])
            );

            if ($maxError < $bestDiff) {
                $bestDiff = $maxError;
                $bestMix = ['foods' => [$pFood, $cFood, $fFood], 'grams' => [$gP, $gC, $gF]];
            }
        }

        if ($bestMix) {
            $this->saveMealItem($meal, $bestMix['foods'][0], $bestMix['grams'][0]);
            $this->saveMealItem($meal, $bestMix['foods'][1], $bestMix['grams'][1]);
            $this->saveMealItem($meal, $bestMix['foods'][2], $bestMix['grams'][2]);
            return;
        }

        throw new \Exception("No se pudo cuadrar los macros de la comida.");
    }

    /**
     * Grams of each food so the plate hits all three macro targets exactly.
     *
     * Solves M·g = t where M[macro][food] is that food's grams-per-100g of the
     * macro and t is [proteina, carbos, grasa]. Returns [gP, gC, gF] in grams,
     * or null if the foods are macro-collinear (singular matrix).
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function solveGrams(Food $p, Food $c, Food $f, array $t): ?array
    {
        // Columns = foods, rows = macros (per gram, i.e. value/100).
        $m = [
            [$p->proteina, $c->proteina, $f->proteina],
            [$p->carbos,   $c->carbos,   $f->carbos],
            [$p->grasa,    $c->grasa,    $f->grasa],
        ];
        $rhs = [$t['proteina'], $t['carbos'], $t['grasa']];

        $det = $this->det3($m);
        if (abs($det) < 1e-6) {
            return null;
        }

        // Cramer's rule, then ×100 (matrix is per-100g, grams = solution×100).
        $grams = [];
        for ($col = 0; $col < 3; $col++) {
            $mc = $m;
            for ($row = 0; $row < 3; $row++) {
                $mc[$row][$col] = $rhs[$row];
            }
            $grams[$col] = ($this->det3($mc) / $det) * 100;
        }
        return $grams;
    }

    private function det3(array $m): float
    {
        return $m[0][0] * ($m[1][1] * $m[2][2] - $m[1][2] * $m[2][1])
             - $m[0][1] * ($m[1][0] * $m[2][2] - $m[1][2] * $m[2][0])
             + $m[0][2] * ($m[1][0] * $m[2][1] - $m[1][1] * $m[2][0]);
    }

    /**
     * A slot counts as breakfast-style (so it uses breakfast foods) when its name
     * is Desayuno, Media mañana or Merienda.
     */
    private function isBreakfastSlot(?string $nombre): bool
    {
        $n = mb_strtolower($nombre ?? '');
        return str_contains($n, 'desayuno') || str_contains($n, 'mañana') || str_contains($n, 'merienda');
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
