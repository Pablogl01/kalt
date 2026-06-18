<?php

use App\Models\User;
use App\Models\Food;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\DailyLog;
use App\Models\MealLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['objetivo' => 'volumen']);
    $this->otherUser = User::factory()->create();

    $this->food = Food::create([
        'nombre' => 'Pollo', 'categoria' => 'proteina',
        'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2,
    ]);

    $this->weeklyPlan = WeeklyPlan::create([
        'user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready',
    ]);

    $this->dayPlan = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id, 'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60,
    ]);

    $this->meal1 = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida 1', 'hora_objetivo' => '14:00:00']);
    $this->meal2 = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida 2', 'hora_objetivo' => '20:00:00']);

    $this->item1 = MealItem::create([
        'meal_id' => $this->meal1->id, 'food_id' => $this->food->id,
        'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2,
    ]);
    $this->item2 = MealItem::create([
        'meal_id' => $this->meal2->id, 'food_id' => $this->food->id,
        'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2,
    ]);

    $this->dailyLog = DailyLog::create([
        'user_id' => $this->user->id, 'fecha' => '2026-06-15',
        'entreno_planificado' => true, 'ha_entrenado' => true,
    ]);

    $this->log1 = MealLog::create(['daily_log_id' => $this->dailyLog->id, 'meal_id' => $this->meal1->id, 'es_extra' => false, 'realizada' => false]);
    $this->log2 = MealLog::create(['daily_log_id' => $this->dailyLog->id, 'meal_id' => $this->meal2->id, 'es_extra' => false, 'realizada' => false]);
});

it('persists a snapshot of the previous state after a recalculation', function () {
    $this->actingAs($this->user)->patchJson("/api/meal-logs/{$this->log1->id}/skip")->assertStatus(200);

    $this->dailyLog->refresh();
    expect($this->dailyLog->recalculo_snapshot)->not->toBeNull();
    expect($this->dailyLog->recalculo_snapshot['meal_items'])->not->toBeEmpty();
});

it('POST /api/daily-logs/{id}/recalc/undo restores the previous quantities and clears the recalc state', function () {
    $this->actingAs($this->user)->patchJson("/api/meal-logs/{$this->log1->id}/skip")->assertStatus(200);

    // The remaining meal was scaled up by the skip.
    expect((float) $this->item2->fresh()->calorias)->toBeGreaterThan(150);

    $this->actingAs($this->user)
        ->postJson("/api/daily-logs/{$this->dailyLog->id}/recalc/undo")
        ->assertStatus(200);

    expect((float) $this->item2->fresh()->cantidad_gramos)->toEqualWithDelta(100, 1);
    expect((float) $this->item2->fresh()->calorias)->toEqualWithDelta(100, 1);

    $this->dailyLog->refresh();
    expect($this->dailyLog->recalculo_motivo)->toBeNull();
    expect($this->dailyLog->recalculo_snapshot)->toBeNull();
});

it('undo without a previous recalculation returns HTTP 422', function () {
    $this->actingAs($this->user)
        ->postJson("/api/daily-logs/{$this->dailyLog->id}/recalc/undo")
        ->assertStatus(422);
});

it('undo on another users daily log returns HTTP 403', function () {
    $this->actingAs($this->user)->patchJson("/api/meal-logs/{$this->log1->id}/skip")->assertStatus(200);

    $this->actingAs($this->otherUser)
        ->postJson("/api/daily-logs/{$this->dailyLog->id}/recalc/undo")
        ->assertStatus(403);
});
