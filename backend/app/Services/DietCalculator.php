<?php

namespace App\Services;

use App\Models\User;

/**
 * Calculates TDEE and macro targets based on user profile.
 *
 * Algorithm: Harris-Benedict BMR → TDEE (activity multiplier) → Macro split by goal.
 * Fully deterministic — no DB access, no external AI.
 *
 * Unit tests: tests/Unit/Services/DietCalculatorTest.php
 */
class DietCalculator
{
    // Activity level multipliers (Harris-Benedict)
    private const ACTIVITY_FACTORS = [
        'sedentario' => 1.2,
        'ligero'     => 1.375,
        'moderado'   => 1.55,
        'alto'       => 1.725,
    ];

    // Calorie adjustments by goal (kcal)
    private const GOAL_ADJUSTMENTS = [
        'volumen'       =>  300,
        'mantenimiento' =>    0,
        'definicion'    => -300,
    ];

    // Macro split percentages by goal (protein%, carbs%, fat%)
    private const MACRO_SPLITS = [
        'volumen'       => ['protein' => 30, 'carbs' => 50, 'fat' => 20],
        'mantenimiento' => ['protein' => 30, 'carbs' => 45, 'fat' => 25],
        'definicion'    => ['protein' => 40, 'carbs' => 35, 'fat' => 25],
    ];

    // Caloric density (kcal per gram)
    private const KCAL_PER_GRAM_PROTEIN = 4;
    private const KCAL_PER_GRAM_CARBS   = 4;
    private const KCAL_PER_GRAM_FAT     = 9;

    /**
     * Calculate Basal Metabolic Rate using the Harris-Benedict equation.
     *
     * Male:   88.36 + (13.4 × weight_kg) + (4.8 × height_cm) − (5.7 × age)
     * Female: 447.6 + (9.2  × weight_kg) + (3.1 × height_cm) − (4.3 × age)
     *
     * Reads from the User model in memory — no DB queries.
     */
    public function calculateBMR(User $user): float
    {
        $weight = (float) $user->peso;
        $height = (float) $user->altura;
        $age    = (int)   $user->edad;

        if ($user->sexo === 'hombre') {
            return 88.36 + (13.4 * $weight) + (4.8 * $height) - (5.7 * $age);
        }

        return 447.6 + (9.2 * $weight) + (3.1 * $height) - (4.3 * $age);
    }

    /**
     * Calculate Total Daily Energy Expenditure by applying the activity multiplier.
     */
    public function calculateTDEE(float $bmr, string $activityLevel): float
    {
        return $bmr * self::ACTIVITY_FACTORS[$activityLevel];
    }

    /**
     * Distribute TDEE calories into macro targets for the given goal.
     *
     * Returns amounts in grams and adjusted calorie total.
     * Protein and carbs: 4 kcal/g — Fat: 9 kcal/g.
     *
     * @return array{calorias: float, proteina: float, carbos: float, grasa: float}
     */
    public function calculateMacros(float $tdee, string $objetivo): array
    {
        $calories = $tdee + self::GOAL_ADJUSTMENTS[$objetivo];
        $split    = self::MACRO_SPLITS[$objetivo];

        return [
            'calorias' => round($calories, 1),
            'proteina' => round(($calories * $split['protein'] / 100) / self::KCAL_PER_GRAM_PROTEIN, 1),
            'carbos'   => round(($calories * $split['carbs']   / 100) / self::KCAL_PER_GRAM_CARBS,   1),
            'grasa'    => round(($calories * $split['fat']     / 100) / self::KCAL_PER_GRAM_FAT,      1),
        ];
    }

    /**
     * Full pipeline: BMR → TDEE → Macros for a given user profile.
     *
     * Requires: sexo, peso, altura, edad, nivel_actividad, objetivo.
     * Returns empty array if any required field is missing.
     *
     * @return array{calorias: float, proteina: float, carbos: float, grasa: float}|array{}
     */
    public function calculate(User $user): array
    {
        if (! $this->userHasCompleteProfile($user)) {
            return [];
        }

        $bmr  = $this->calculateBMR($user);
        $tdee = $this->calculateTDEE($bmr, $user->nivel_actividad);

        return $this->calculateMacros($tdee, $user->objetivo);
    }

    /**
     * Check that all fields required for calculation are present.
     */
    public function userHasCompleteProfile(User $user): bool
    {
        return ! empty($user->sexo)
            && ! empty($user->peso)
            && ! empty($user->altura)
            && ! empty($user->edad)
            && ! empty($user->nivel_actividad)
            && ! empty($user->objetivo);
    }
}
