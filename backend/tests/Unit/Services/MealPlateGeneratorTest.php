<?php

use App\Models\DayPlan;
use App\Models\Food;
use App\Models\Meal;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\MealPlateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->generator = new MealPlateGenerator();
    $this->user = User::factory()->create();

    // One isolated food per macro so the random pick is deterministic and each
    // macro is contributed by a single food.
    $this->protein = Food::create(['nombre' => 'Pollo',  'categoria' => 'proteina', 'calorias' => 120, 'proteina' => 30, 'carbos' => 0,  'grasa' => 0]);
    $this->carb    = Food::create(['nombre' => 'Arroz',  'categoria' => 'carbos',   'calorias' => 200, 'proteina' => 0,  'carbos' => 50, 'grasa' => 0]);
    $this->fat     = Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas',   'calorias' => 450, 'proteina' => 0,  'carbos' => 0,  'grasa' => 50]);

    $plan = WeeklyPlan::create(['user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $dayPlan = DayPlan::create(['weekly_plan_id' => $plan->id, 'fecha' => '2026-06-15', 'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60]);
    $this->meal = Meal::create(['day_plan_id' => $dayPlan->id, 'nombre' => 'Almuerzo']);
});

it('builds a meal of three items matching the target macros', function () {
    $this->generator->build($this->meal, ['proteina' => 30, 'carbos' => 50, 'grasa' => 15], $this->user);

    $items = $this->meal->mealItems()->get();
    expect($items)->toHaveCount(3);
    expect((float) $items->sum('proteina'))->toEqualWithDelta(30, 0.5);
    expect((float) $items->sum('carbos'))->toEqualWithDelta(50, 0.5);
    expect((float) $items->sum('grasa'))->toEqualWithDelta(15, 0.5);
});

it('injects a fixed supplement item and squares the rest around it', function () {
    $whey = Food::create(['nombre' => 'Whey', 'categoria' => 'proteina', 'calorias' => 380, 'proteina' => 80, 'carbos' => 0, 'grasa' => 0]);

    $this->generator->build(
        $this->meal,
        ['proteina' => 30, 'carbos' => 50, 'grasa' => 15],
        $this->user,
        [['food' => $whey, 'grams' => 25]] // 25g whey = 20g protein
    );

    $items = $this->meal->mealItems()->get();

    // The whey item is kept verbatim.
    $wheyItem = $items->firstWhere('food_id', $whey->id);
    expect($wheyItem)->not->toBeNull();
    expect((float) $wheyItem->cantidad_gramos)->toEqualWithDelta(25, 0.1);
    expect((float) $wheyItem->proteina)->toEqualWithDelta(20, 0.5);

    // Whey + protein/carb/fat foods, and totals still hit the target.
    expect($items->count())->toBeGreaterThanOrEqual(4);
    expect((float) $items->sum('proteina'))->toEqualWithDelta(30, 0.5);
    expect((float) $items->sum('carbos'))->toEqualWithDelta(50, 0.5);
    expect((float) $items->sum('grasa'))->toEqualWithDelta(15, 0.5);
});
