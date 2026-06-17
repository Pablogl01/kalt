<?php

use App\Models\User;
use App\Models\Food;
use App\Models\MealItem;
use App\Models\Meal;
use App\Models\DayPlan;
use App\Models\WeeklyPlan;
use App\Services\ShoppingGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->generator = new ShoppingGenerator();
    $this->user = User::factory()->create();

    $this->foodP = Food::create([
        'nombre' => 'Pollo',
        'categoria' => 'proteina',
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $this->foodC = Food::create([
        'nombre' => 'Arroz',
        'categoria' => 'carbos',
        'calorias' => 130,
        'proteina' => 2.7,
        'carbos' => 28,
        'grasa' => 0.3
    ]);

    $this->weeklyPlan = WeeklyPlan::create([
        'user_id' => $this->user->id,
        'semana_inicio' => '2026-06-15',
        'status' => 'ready'
    ]);
});

it('groups foods correctly by category and sums quantities', function () {
    // Create day plan 1 with meals and items
    $day1 = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000,
        'proteina_obj' => 150,
        'carbos_obj' => 200,
        'grasa_obj' => 60
    ]);
    
    $meal1 = Meal::create(['day_plan_id' => $day1->id, 'nombre' => 'Almuerzo']);
    MealItem::create([
        'meal_id' => $meal1->id,
        'food_id' => $this->foodP->id,
        'cantidad_gramos' => 150,
        'calorias' => 150,
        'proteina' => 30,
        'carbos' => 0,
        'grasa' => 3
    ]);
    MealItem::create([
        'meal_id' => $meal1->id,
        'food_id' => $this->foodC->id,
        'cantidad_gramos' => 100,
        'calorias' => 130,
        'proteina' => 2.7,
        'carbos' => 28,
        'grasa' => 0.3
    ]);

    // Create day plan 2 with meals and items
    $day2 = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'fecha' => '2026-06-16',
        'calorias_objetivo' => 2000,
        'proteina_obj' => 150,
        'carbos_obj' => 200,
        'grasa_obj' => 60
    ]);
    
    $meal2 = Meal::create(['day_plan_id' => $day2->id, 'nombre' => 'Cena']);
    MealItem::create([
        'meal_id' => $meal2->id,
        'food_id' => $this->foodP->id,
        'cantidad_gramos' => 200,
        'calorias' => 200,
        'proteina' => 40,
        'carbos' => 0,
        'grasa' => 4
    ]);

    $list = $this->generator->generate($this->weeklyPlan);

    expect($list)->not->toBeNull();
    expect($list->weekly_plan_id)->toBe($this->weeklyPlan->id);
    expect($list->shoppingItems)->toHaveCount(2);

    $polloItem = $list->shoppingItems()->where('food_id', $this->foodP->id)->first();
    expect($polloItem)->not->toBeNull();
    expect((float)$polloItem->cantidad_total)->toEqualWithDelta(350.0, 0.1);
    expect($polloItem->categoria)->toBe('proteina');

    $arrozItem = $list->shoppingItems()->where('food_id', $this->foodC->id)->first();
    expect($arrozItem)->not->toBeNull();
    expect((float)$arrozItem->cantidad_total)->toEqualWithDelta(100.0, 0.1);
    expect($arrozItem->categoria)->toBe('carbos');
});

it('generates an empty list without errors if plan has 0 meal items', function () {
    $list = $this->generator->generate($this->weeklyPlan);
    expect($list)->not->toBeNull();
    expect($list->shoppingItems)->toHaveCount(0);
});

it('regenerates an existing list by deleting and recreating it without duplicating items', function () {
    $day = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000,
        'proteina_obj' => 150,
        'carbos_obj' => 200,
        'grasa_obj' => 60
    ]);
    $meal = Meal::create(['day_plan_id' => $day->id, 'nombre' => 'Almuerzo']);
    MealItem::create([
        'meal_id' => $meal->id,
        'food_id' => $this->foodP->id,
        'cantidad_gramos' => 150,
        'calorias' => 150,
        'proteina' => 30,
        'carbos' => 0,
        'grasa' => 3
    ]);

    // Generate first time
    $list1 = $this->generator->generate($this->weeklyPlan);
    expect($list1->shoppingItems)->toHaveCount(1);

    // Regenerate
    $list2 = $this->generator->regenerate($this->weeklyPlan);
    expect($list2->shoppingItems)->toHaveCount(1);
    
    // Check that first list's items were cascade deleted
    expect(\App\Models\ShoppingList::count())->toBe(1);
    expect(\App\Models\ShoppingItem::count())->toBe(1);
});
