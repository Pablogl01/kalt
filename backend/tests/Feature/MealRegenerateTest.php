<?php

use App\Models\DayPlan;
use App\Models\Food;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;
use App\Models\UserSupplement;
use App\Models\WeeklyPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->protein = Food::create(['nombre' => 'Pollo',  'categoria' => 'proteina', 'calorias' => 120, 'proteina' => 30, 'carbos' => 0,  'grasa' => 0]);
    $this->carb    = Food::create(['nombre' => 'Arroz',  'categoria' => 'carbos',   'calorias' => 200, 'proteina' => 0,  'carbos' => 50, 'grasa' => 0]);
    $this->fat     = Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas',   'calorias' => 450, 'proteina' => 0,  'carbos' => 0,  'grasa' => 50]);

    $this->plan = WeeklyPlan::create(['user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $this->dayPlan = DayPlan::create(['weekly_plan_id' => $this->plan->id, 'fecha' => '2026-06-15', 'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60]);
    $this->meal = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Almuerzo']);

    // Initial plate: 30P / 50C / 15G
    MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $this->protein->id, 'cantidad_gramos' => 100, 'calorias' => 120, 'proteina' => 30, 'carbos' => 0,  'grasa' => 0]);
    MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $this->carb->id,    'cantidad_gramos' => 100, 'calorias' => 200, 'proteina' => 0,  'carbos' => 50, 'grasa' => 0]);
    MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $this->fat->id,     'cantidad_gramos' => 30,  'calorias' => 135, 'proteina' => 0,  'carbos' => 0,  'grasa' => 15]);
});

it('regenerates a meal preserving its macro target', function () {
    $this->actingAs($this->user)
        ->postJson("/api/meals/{$this->meal->id}/regenerate")
        ->assertStatus(200)
        ->assertJsonStructure(['id', 'meal_items']);

    $items = $this->meal->mealItems()->get();
    expect($items)->not->toBeEmpty();
    expect((float) $items->sum('proteina'))->toEqualWithDelta(30, 1.0);
    expect((float) $items->sum('carbos'))->toEqualWithDelta(50, 1.0);
    expect((float) $items->sum('grasa'))->toEqualWithDelta(15, 1.0);
});

it('returns 403 when regenerating another users meal', function () {
    $this->actingAs($this->otherUser)
        ->postJson("/api/meals/{$this->meal->id}/regenerate")
        ->assertStatus(403);
});

it('keeps the supplement item fixed while rerolling the rest', function () {
    $whey = Food::create(['nombre' => 'Whey', 'categoria' => 'proteina', 'calorias' => 380, 'proteina' => 80, 'carbos' => 0, 'grasa' => 0]);
    UserSupplement::create([
        'user_id' => $this->user->id,
        'food_id' => $whey->id,
        'dosis_gramos' => 25,
        'momento' => 'post_entreno',
        'afecta_macros' => true,
    ]);
    // Add the whey item to the meal (as the generator would have).
    $wheyItem = MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $whey->id, 'cantidad_gramos' => 25, 'calorias' => 95, 'proteina' => 20, 'carbos' => 0, 'grasa' => 0]);

    $this->actingAs($this->user)
        ->postJson("/api/meals/{$this->meal->id}/regenerate")
        ->assertStatus(200);

    // The whey item must survive untouched.
    expect(MealItem::find($wheyItem->id))->not->toBeNull();
});
