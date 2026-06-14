<?php

use App\Services\MealDistributor;

beforeEach(function () {
    $this->distributor = new MealDistributor();
});

it('distributes macros correctly among 3 standard meals', function () {
    $macros = [
        'calorias' => 2000.0,
        'proteina' => 150.0,
        'carbos'   => 200.0,
        'grasa'    => 66.6,
    ];

    $mealSlots = [
        ['id' => '1', 'es_pre_entreno' => false, 'es_post_entreno' => false],
        ['id' => '2', 'es_pre_entreno' => false, 'es_post_entreno' => false],
        ['id' => '3', 'es_pre_entreno' => false, 'es_post_entreno' => false],
    ];

    $result = $this->distributor->distribute($macros, 3, $mealSlots);

    expect(count($result))->toBe(3);
    
    $sumCal = 0; $sumProt = 0; $sumCarb = 0; $sumFat = 0;
    foreach ($result as $slotId => $item) {
        $sumCal += $item['calorias'];
        $sumProt += $item['proteina'];
        $sumCarb += $item['carbos'];
        $sumFat += $item['grasa'];
    }

    expect(abs($sumCal - 2000.0))->toBeLessThan(0.5);
    expect(abs($sumProt - 150.0))->toBeLessThan(0.5);
    expect(abs($sumCarb - 200.0))->toBeLessThan(0.5);
    expect(abs($sumFat - 66.6))->toBeLessThan(0.5);
});

it('distributes macros correctly among 5 meals with 1 pre-workout and 1 post-workout meal', function () {
    $macros = [
        'calorias' => 2500.0,
        'proteina' => 187.5,
        'carbos'   => 281.2,
        'grasa'    => 69.4,
    ];

    $mealSlots = [
        ['id' => '1', 'es_pre_entreno' => false, 'es_post_entreno' => false],
        ['id' => '2', 'es_pre_entreno' => true, 'es_post_entreno' => false],
        ['id' => '3', 'es_pre_entreno' => false, 'es_post_entreno' => true],
        ['id' => '4', 'es_pre_entreno' => false, 'es_post_entreno' => false],
        ['id' => '5', 'es_pre_entreno' => false, 'es_post_entreno' => false],
    ];

    $result = $this->distributor->distribute($macros, 5, $mealSlots);

    expect(count($result))->toBe(5);

    // Kcal per meal should be 2500 / 5 = 500 kcal
    // Pre-workout (slot 2) should have:
    // Protein = (500 * 0.35) / 4 = 43.75 -> round to 43.8
    // Carbs = (500 * 0.40) / 4 = 50.0 -> round to 50.0
    // Fat = (500 * 0.25) / 9 = 13.88 -> round to 13.9
    expect($result['2']['calorias'])->toBe(500.0);
    expect($result['2']['proteina'])->toBe(43.8);
    expect($result['2']['carbos'])->toBe(50.0);
    expect($result['2']['grasa'])->toBe(13.9);

    // Post-workout (slot 3) should have:
    // Protein = (500 * 0.45) / 4 = 56.25 -> round to 56.3
    // Carbs = (500 * 0.35) / 4 = 43.75 -> round to 43.8
    // Fat = (500 * 0.20) / 9 = 11.11 -> round to 11.1
    expect($result['3']['calorias'])->toBe(500.0);
    expect($result['3']['proteina'])->toBe(56.3);
    expect($result['3']['carbos'])->toBe(43.8);
    expect($result['3']['grasa'])->toBe(11.1);

    // Rounding check
    $sumCal = 0; $sumProt = 0; $sumCarb = 0; $sumFat = 0;
    foreach ($result as $item) {
        $sumCal += $item['calorias'];
        $sumProt += $item['proteina'];
        $sumCarb += $item['carbos'];
        $sumFat += $item['grasa'];
    }

    expect(abs($sumCal - 2500.0))->toBeLessThan(0.5);
    expect(abs($sumProt - 187.5))->toBeLessThan(0.5);
    expect(abs($sumCarb - 281.2))->toBeLessThan(0.5);
    expect(abs($sumFat - 69.4))->toBeLessThan(0.5);
});

it('works without errors with minimum 2 meals', function () {
    $macros = [
        'calorias' => 1800.0,
        'proteina' => 120.0,
        'carbos'   => 180.0,
        'grasa'    => 50.0,
    ];

    $mealSlots = [
        ['id' => '1', 'es_pre_entreno' => false, 'es_post_entreno' => false],
        ['id' => '2', 'es_pre_entreno' => false, 'es_post_entreno' => false],
    ];

    $result = $this->distributor->distribute($macros, 2, $mealSlots);

    expect(count($result))->toBe(2);
    expect($result['1']['calorias'])->toBe(900.0);
    expect($result['2']['calorias'])->toBe(900.0);
});

it('works without errors with maximum 7 meals', function () {
    $macros = [
        'calorias' => 3000.0,
        'proteina' => 200.0,
        'carbos'   => 350.0,
        'grasa'    => 80.0,
    ];

    $mealSlots = [];
    for ($i = 1; $i <= 7; $i++) {
        $mealSlots[] = ['id' => (string)$i, 'es_pre_entreno' => false, 'es_post_entreno' => false];
    }

    $result = $this->distributor->distribute($macros, 7, $mealSlots);

    expect(count($result))->toBe(7);
});
