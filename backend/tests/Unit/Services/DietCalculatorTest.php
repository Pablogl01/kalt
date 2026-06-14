<?php

use App\Models\User;
use App\Services\DietCalculator;

beforeEach(function () {
    $this->calculator = new DietCalculator();
});

// ──────────────────────────────────────────────
// BMR
// ──────────────────────────────────────────────

it('calculates correct BMR for a male', function () {
    // 88.36 + (13.4 × 80) + (4.8 × 180) − (5.7 × 30)
    // = 88.36 + 1072 + 864 − 171 = 1853.36
    $user = new User(['sexo' => 'hombre', 'peso' => 80, 'altura' => 180, 'edad' => 30]);

    expect($this->calculator->calculateBMR($user))->toEqual(1853.36);
});

it('calculates correct BMR for a female', function () {
    // 447.6 + (9.2 × 60) + (3.1 × 165) − (4.3 × 25)
    // = 447.6 + 552 + 511.5 − 107.5 = 1403.6
    $user = new User(['sexo' => 'mujer', 'peso' => 60, 'altura' => 165, 'edad' => 25]);

    expect($this->calculator->calculateBMR($user))->toEqual(1403.6);
});

// ──────────────────────────────────────────────
// TDEE — all four activity levels
// ──────────────────────────────────────────────

it('calculates TDEE with sedentary factor', function () {
    expect($this->calculator->calculateTDEE(2000, 'sedentario'))->toEqual(2400.0);
});

it('calculates TDEE with light activity factor', function () {
    expect($this->calculator->calculateTDEE(2000, 'ligero'))->toEqual(2750.0);
});

it('calculates TDEE with moderate activity factor', function () {
    expect($this->calculator->calculateTDEE(2000, 'moderado'))->toEqual(3100.0);
});

it('calculates TDEE with high activity factor', function () {
    expect($this->calculator->calculateTDEE(2000, 'alto'))->toEqual(3450.0);
});

// ──────────────────────────────────────────────
// Macros — all three goals
// ──────────────────────────────────────────────

it('calculates correct macros for volumen (30/50/20)', function () {
    // TDEE 2500 + 300 = 2800 kcal
    // Protein: 2800 × 0.30 / 4 = 210 g
    // Carbs:   2800 × 0.50 / 4 = 350 g
    // Fat:     2800 × 0.20 / 9 ≈ 62.2 g
    $macros = $this->calculator->calculateMacros(2500, 'volumen');

    expect($macros['calorias'])->toEqual(2800.0)
        ->and($macros['proteina'])->toEqual(210.0)
        ->and($macros['carbos'])->toEqual(350.0)
        ->and($macros['grasa'])->toEqual(62.2);
});

it('calculates correct macros for mantenimiento (30/45/25)', function () {
    // TDEE 2500 + 0 = 2500 kcal
    // Protein: 2500 × 0.30 / 4 = 187.5 g
    // Carbs:   2500 × 0.45 / 4 = 281.25 → 281.3 g
    // Fat:     2500 × 0.25 / 9 ≈ 69.4 g
    $macros = $this->calculator->calculateMacros(2500, 'mantenimiento');

    expect($macros['calorias'])->toEqual(2500.0)
        ->and($macros['proteina'])->toEqual(187.5)
        ->and($macros['carbos'])->toEqual(281.3)
        ->and($macros['grasa'])->toEqual(69.4);
});

it('calculates correct macros for definicion (40/35/25)', function () {
    // TDEE 2500 - 300 = 2200 kcal
    // Protein: 2200 × 0.40 / 4 = 220 g
    // Carbs:   2200 × 0.35 / 4 = 192.5 g
    // Fat:     2200 × 0.25 / 9 ≈ 61.1 g
    $macros = $this->calculator->calculateMacros(2200 + 300, 'definicion');

    expect($macros['calorias'])->toEqual(2200.0)
        ->and($macros['proteina'])->toEqual(220.0)
        ->and($macros['carbos'])->toEqual(192.5)
        ->and($macros['grasa'])->toEqual(61.1);
});

it('macro calories sum matches the adjusted TDEE within 1 kcal tolerance', function () {
    foreach (['volumen', 'mantenimiento', 'definicion'] as $objetivo) {
        $tdee   = 2500.0;
        $macros = $this->calculator->calculateMacros($tdee, $objetivo);

        $reconstituted = ($macros['proteina'] * 4)
            + ($macros['carbos'] * 4)
            + ($macros['grasa'] * 9);

        expect(abs($reconstituted - $macros['calorias']))->toBeLessThanOrEqual(1.0,
            "Macro calorie reconstitution failed for objetivo '$objetivo'"
        );
    }
});

// ──────────────────────────────────────────────
// Edge cases
// ──────────────────────────────────────────────

it('does not throw for very low weight (< 40 kg)', function () {
    $user = new User(['sexo' => 'mujer', 'peso' => 35, 'altura' => 155, 'edad' => 20]);

    expect(fn () => $this->calculator->calculateBMR($user))->not->toThrow(Throwable::class);
});

it('does not throw for very high weight (> 200 kg)', function () {
    $user = new User(['sexo' => 'hombre', 'peso' => 210, 'altura' => 190, 'edad' => 40]);

    expect(fn () => $this->calculator->calculateBMR($user))->not->toThrow(Throwable::class);
});
