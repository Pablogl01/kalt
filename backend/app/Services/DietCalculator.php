<?php

namespace App\Services;

/**
 * Calculates TDEE and macro targets based on user profile.
 * Implements: Harris-Benedict BMR + activity multiplier + goal adjustment.
 * Fully deterministic — no external AI.
 * Unit tests: tests/Unit/Services/DietCalculatorTest.php (Phase 1)
 */
class DietCalculator
{
    // Activity level multipliers
    private const ACTIVITY_FACTORS = [
        'sedentario'    => 1.2,
        'ligero'        => 1.375,
        'moderado'      => 1.55,
        'alto'          => 1.725,
    ];

    // Calorie adjustments by goal
    private const GOAL_ADJUSTMENTS = [
        'volumen'        =>  300,
        'mantenimiento'  =>    0,
        'definicion'     => -300,
    ];

    // Macro split by goal (protein%, carbs%, fat%)
    private const MACRO_SPLITS = [
        'volumen'       => ['protein' => 30, 'carbs' => 50, 'fat' => 20],
        'mantenimiento' => ['protein' => 30, 'carbs' => 45, 'fat' => 25],
        'definicion'    => ['protein' => 40, 'carbs' => 35, 'fat' => 25],
    ];

    // To be implemented in Phase 1
    public function calculate(array $profile): array
    {
        // TODO: Phase 1
        return [];
    }
}
