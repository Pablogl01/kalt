<?php

use App\Models\User;
use App\Models\Food;
use App\Models\FoodSubstitute;
use App\Models\UserFoodRestriction;
use App\Models\MealItem;
use App\Models\Meal;
use App\Models\DayPlan;
use App\Models\WeeklyPlan;
use App\Services\SubstituteEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->engine = new SubstituteEngine();

    $this->user = User::factory()->create();

    // Create some foods
    $this->foodOriginal = Food::create([
        'nombre' => 'Pollo',
        'categoria' => 'proteina',
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $this->foodSub1 = Food::create([
        'nombre' => 'Pavo',
        'categoria' => 'proteina',
        'calorias' => 110,
        'proteina' => 22,
        'carbos' => 0,
        'grasa' => 1.5
    ]);

    $this->foodSub2 = Food::create([
        'nombre' => 'Atun',
        'categoria' => 'proteina',
        'calorias' => 120,
        'proteina' => 24,
        'carbos' => 0,
        'grasa' => 2.5
    ]);

    $this->foodRestricted = Food::create([
        'nombre' => 'Huevo',
        'categoria' => 'proteina',
        'calorias' => 150,
        'proteina' => 13,
        'carbos' => 1,
        'grasa' => 10
    ]);

    // Create substitute relations
    FoodSubstitute::create([
        'food_id' => $this->foodOriginal->id,
        'substitute_food_id' => $this->foodSub1->id,
        'similitud_macros' => 95.0
    ]);

    FoodSubstitute::create([
        'food_id' => $this->foodOriginal->id,
        'substitute_food_id' => $this->foodSub2->id,
        'similitud_macros' => 90.0
    ]);

    FoodSubstitute::create([
        'food_id' => $this->foodOriginal->id,
        'substitute_food_id' => $this->foodRestricted->id,
        'similitud_macros' => 85.0
    ]);
});

it('filters out restricted substitutes (allergy and intolerance)', function () {
    // 1. Mark Huevo as allergy
    UserFoodRestriction::create([
        'user_id' => $this->user->id,
        'food_id' => $this->foodRestricted->id,
        'tipo' => 'alergia'
    ]);

    // 2. Mark Atun as intolerance
    UserFoodRestriction::create([
        'user_id' => $this->user->id,
        'food_id' => $this->foodSub2->id,
        'tipo' => 'intolerancia'
    ]);

    $substitutes = $this->engine->getSubstitutes($this->foodOriginal->id, $this->user->id);

    // Only Pavo should be left
    expect($substitutes)->toHaveCount(1);
    expect($substitutes[0]['id'])->toBe($this->foodSub1->id);
});

it('returns substitutes ordered by similitud_macros descending', function () {
    $substitutes = $this->engine->getSubstitutes($this->foodOriginal->id, $this->user->id);

    expect($substitutes)->toHaveCount(3);
    expect((float)$substitutes[0]['similitud_macros'])->toBe(95.0);
    expect((float)$substitutes[1]['similitud_macros'])->toBe(90.0);
    expect((float)$substitutes[2]['similitud_macros'])->toBe(85.0);
});

it('returns empty array if no substitutes found', function () {
    $foodNoSub = Food::create([
        'nombre' => 'Agua',
        'categoria' => 'otros',
        'calorias' => 0,
        'proteina' => 0,
        'carbos' => 0,
        'grasa' => 0
    ]);

    $substitutes = $this->engine->getSubstitutes($foodNoSub->id, $this->user->id);
    expect($substitutes)->toBeEmpty();
});

it('throws exception when applying invalid substitute', function () {
    $weeklyPlan = WeeklyPlan::create(['user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $dayPlan = DayPlan::create(['weekly_plan_id' => $weeklyPlan->id, 'fecha' => '2026-06-15', 'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60]);
    $meal = Meal::create(['day_plan_id' => $dayPlan->id, 'nombre' => 'Almuerzo']);
    $item = MealItem::create([
        'meal_id' => $meal->id,
        'food_id' => $this->foodOriginal->id,
        'cantidad_gramos' => 100,
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $invalidFood = Food::create(['nombre' => 'Manzana', 'categoria' => 'carbos', 'calorias' => 50, 'proteina' => 0, 'carbos' => 12, 'grasa' => 0]);

    expect(fn () => $this->engine->applySubstitute($item, $invalidFood->id, $this->user->id))
        ->toThrow(\InvalidArgumentException::class);
});

it('recalculates grams correctly maintaining calories when applying substitute', function () {
    $weeklyPlan = WeeklyPlan::create(['user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $dayPlan = DayPlan::create(['weekly_plan_id' => $weeklyPlan->id, 'fecha' => '2026-06-15', 'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60]);
    $meal = Meal::create(['day_plan_id' => $dayPlan->id, 'nombre' => 'Almuerzo']);
    $item = MealItem::create([
        'meal_id' => $meal->id,
        'food_id' => $this->foodOriginal->id,
        'cantidad_gramos' => 100, // 100g of pollo = 100 kcal
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    // Apply Pavo (110 kcal per 100g)
    // Grams of Pavo to get 100 kcal = (100 / 110) * 100 = 90.91g
    $updatedItem = $this->engine->applySubstitute($item, $this->foodSub1->id, $this->user->id);

    expect($updatedItem->food_id)->toBe($this->foodSub1->id);
    expect((float)$updatedItem->cantidad_gramos)->toEqualWithDelta(90.91, 0.05);
    expect((float)$updatedItem->calorias)->toEqualWithDelta(100.0, 0.5);
    // Macros check
    // Protein: 22 * 0.9091 = 20g
    expect((float)$updatedItem->proteina)->toEqualWithDelta(20.0, 0.5);
});
