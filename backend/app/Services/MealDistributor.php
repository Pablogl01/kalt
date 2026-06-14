<?php

namespace App\Services;

class MealDistributor
{
    private const KCAL_PER_GRAM_PROTEIN = 4;
    private const KCAL_PER_GRAM_CARBS   = 4;
    private const KCAL_PER_GRAM_FAT     = 9;

    /**
     * Distribute target daily macros and calories among the user's meal slots.
     *
     * Pre-workout meals: 35% Protein, 40% Carbs, 25% Fat of their share of calories.
     * Post-workout meals: 45% Protein, 35% Carbs, 20% Fat of their share of calories.
     * Standard meals: receive remaining macros and calories divided equally.
     *
     * @param array{calorias: float, proteina: float, carbos: float, grasa: float} $macros
     * @param int $numComidas
     * @param array $mealSlots
     * @return array
     */
    public function distribute(array $macros, int $numComidas, array $mealSlots): array
    {
        if ($numComidas <= 0 || empty($mealSlots)) {
            return [];
        }

        // Each meal has equal share of the daily calories
        $kcalPerMeal = $macros['calorias'] / $numComidas;

        $distribution = [];
        $prePostCalories = 0;
        $prePostProtein = 0;
        $prePostCarbs = 0;
        $prePostFat = 0;
        $prePostCount = 0;

        // First pass: calculate pre/post workout meals
        foreach ($mealSlots as $slot) {
            $isPre = (bool)($slot['es_pre_entreno'] ?? false);
            $isPost = (bool)($slot['es_post_entreno'] ?? false);

            if ($isPre || $isPost) {
                $prePostCount++;
                $calories = $kcalPerMeal;

                if ($isPre) {
                    $protein = ($calories * 0.35) / self::KCAL_PER_GRAM_PROTEIN;
                    $carbs = ($calories * 0.40) / self::KCAL_PER_GRAM_CARBS;
                    $fat = ($calories * 0.25) / self::KCAL_PER_GRAM_FAT;
                } else {
                    $protein = ($calories * 0.45) / self::KCAL_PER_GRAM_PROTEIN;
                    $carbs = ($calories * 0.35) / self::KCAL_PER_GRAM_CARBS;
                    $fat = ($calories * 0.20) / self::KCAL_PER_GRAM_FAT;
                }

                $distribution[$slot['id']] = [
                    'calorias' => round($calories, 1),
                    'proteina' => round($protein, 1),
                    'carbos'   => round($carbs, 1),
                    'grasa'    => round($fat, 1),
                ];

                $prePostCalories += round($calories, 1);
                $prePostProtein += round($protein, 1);
                $prePostCarbs += round($carbs, 1);
                $prePostFat += round($fat, 1);
            }
        }

        // Second pass: distribute remaining macros to standard meals
        $standardCount = $numComidas - $prePostCount;
        $standardSlots = [];
        foreach ($mealSlots as $slot) {
            $isPre = (bool)($slot['es_pre_entreno'] ?? false);
            $isPost = (bool)($slot['es_post_entreno'] ?? false);
            if (!$isPre && !$isPost) {
                $standardSlots[] = $slot;
            }
        }

        if ($standardCount > 0) {
            $remainingCalories = max(0, $macros['calorias'] - $prePostCalories);
            $remainingProtein = max(0, $macros['proteina'] - $prePostProtein);
            $remainingCarbs = max(0, $macros['carbos'] - $prePostCarbs);
            $remainingFat = max(0, $macros['grasa'] - $prePostFat);

            foreach ($standardSlots as $slot) {
                $distribution[$slot['id']] = [
                    'calorias' => round($remainingCalories / $standardCount, 1),
                    'proteina' => round($remainingProtein / $standardCount, 1),
                    'carbos'   => round($remainingCarbs / $standardCount, 1),
                    'grasa'    => round($remainingFat / $standardCount, 1),
                ];
            }
        }

        // Adjust rounding errors on the last meal slot to ensure sums match target
        if (!empty($distribution)) {
            $lastSlotId = array_key_last($distribution);
            
            // Calculate current sums
            $sumCal = 0; $sumProt = 0; $sumCarb = 0; $sumFat = 0;
            foreach ($distribution as $item) {
                $sumCal += $item['calorias'];
                $sumProt += $item['proteina'];
                $sumCarb += $item['carbos'];
                $sumFat += $item['grasa'];
            }

            $diffCal = $macros['calorias'] - $sumCal;
            $diffProt = $macros['proteina'] - $sumProt;
            $diffCarb = $macros['carbos'] - $sumCarb;
            $diffFat = $macros['grasa'] - $sumFat;

            $distribution[$lastSlotId]['calorias'] = round($distribution[$lastSlotId]['calorias'] + $diffCal, 1);
            $distribution[$lastSlotId]['proteina'] = round($distribution[$lastSlotId]['proteina'] + $diffProt, 1);
            $distribution[$lastSlotId]['carbos'] = round($distribution[$lastSlotId]['carbos'] + $diffCarb, 1);
            $distribution[$lastSlotId]['grasa'] = round($distribution[$lastSlotId]['grasa'] + $diffFat, 1);
        }

        return $distribution;
    }
}
