<?php

use App\Models\User;
use App\Models\Food;
use App\Models\FoodSubstitute;
use App\Models\UserFoodRestriction;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\DailyLog;
use App\Models\MealLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'nivel_actividad' => 'moderado',
        'objetivo' => 'volumen'
    ]);

    $this->otherUser = User::factory()->create();

    $this->food = Food::create([
        'nombre' => 'Pollo',
        'categoria' => 'proteina',
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $this->substituteFood = Food::create([
        'nombre' => 'Pavo',
        'categoria' => 'proteina',
        'calorias' => 110,
        'proteina' => 22,
        'carbos' => 0,
        'grasa' => 1.5
    ]);

    FoodSubstitute::create([
        'food_id' => $this->food->id,
        'substitute_food_id' => $this->substituteFood->id,
        'similitud_macros' => 95.0
    ]);

    $this->weeklyPlan = WeeklyPlan::create([
        'user_id' => $this->user->id,
        'semana_inicio' => '2026-06-15',
        'status' => 'ready'
    ]);

    $this->dayPlan = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000,
        'proteina_obj' => 150,
        'carbos_obj' => 200,
        'grasa_obj' => 60
    ]);

    $this->meal = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00']);

    $this->mealItem = MealItem::create([
        'meal_id' => $this->meal->id,
        'food_id' => $this->food->id,
        'cantidad_gramos' => 100,
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $this->dailyLog = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-15',
        'entreno_planificado' => true,
        'ha_entrenado' => true,
    ]);

    $this->mealLog = MealLog::create([
        'daily_log_id' => $this->dailyLog->id,
        'meal_id' => $this->meal->id,
        'es_extra' => false,
        'realizada' => false
    ]);
});

it('PATCH /api/meal-logs/{id}/skip triggers recalculation and returns mensaje_recalculo', function () {
    $this->actingAs($this->user)
        ->patchJson("/api/meal-logs/{$this->mealLog->id}/skip")
        ->assertStatus(200)
        ->assertJsonStructure(['mensaje_recalculo', 'recalculo_motivo'])
        ->assertJson([
            'recalculo_motivo' => 'comida_saltada'
        ]);
});

it('PATCH /api/daily-logs/{id}/training with ha_entrenado=false in planned day triggers Case B', function () {
    $this->actingAs($this->user)
        ->patchJson("/api/daily-logs/{$this->dailyLog->id}/training", [
            'ha_entrenado' => false
        ])
        ->assertStatus(200)
        ->assertJson([
            'recalculo_motivo' => 'no_ha_entrenado'
        ]);
});

it('PATCH /api/daily-logs/{id}/training with ha_entrenado=true in rest day without gym hour returns HTTP 422', function () {
    $this->dailyLog->update(['entreno_planificado' => false, 'ha_entrenado' => false]);

    $this->actingAs($this->user)
        ->patchJson("/api/daily-logs/{$this->dailyLog->id}/training", [
            'ha_entrenado' => true,
            'hora_gimnasio' => null
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hora_gimnasio']);
});

it('GET /api/meal-items/{id}/substitutes of another users item returns HTTP 403', function () {
    $this->actingAs($this->otherUser)
        ->getJson("/api/meal-items/{$this->mealItem->id}/substitutes")
        ->assertStatus(403);
});

it('PATCH /api/meal-items/{id}/substitute with invalid substitute returns HTTP 422', function () {
    $invalidFood = Food::create(['nombre' => 'Manzana', 'categoria' => 'carbos', 'calorias' => 50, 'proteina' => 0, 'carbos' => 12, 'grasa' => 0]);

    $this->actingAs($this->user)
        ->patchJson("/api/meal-items/{$this->mealItem->id}/substitute", [
            'substitute_food_id' => $invalidFood->id
        ])
        ->assertStatus(422);
});

it('PATCH /api/meal-items/{id}/substitute with restricted food returns HTTP 422', function () {
    UserFoodRestriction::create([
        'user_id' => $this->user->id,
        'food_id' => $this->substituteFood->id,
        'tipo' => 'alergia'
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/meal-items/{$this->mealItem->id}/substitute", [
            'substitute_food_id' => $this->substituteFood->id
        ])
        ->assertStatus(422);
});

it('PATCH /api/meal-items/{id}/substitute valid updates MealItem and recalculates grams', function () {
    $this->actingAs($this->user)
        ->patchJson("/api/meal-items/{$this->mealItem->id}/substitute", [
            'substitute_food_id' => $this->substituteFood->id
        ])
        ->assertStatus(200)
        ->assertJson([
            'food_id' => $this->substituteFood->id
        ]);

    $this->mealItem->refresh();
    expect($this->mealItem->food_id)->toBe($this->substituteFood->id);
    expect((float)$this->mealItem->cantidad_gramos)->toEqualWithDelta(90.91, 0.05);
});
