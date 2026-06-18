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
        'user_id' => $this->user->id,
        'semana_inicio' => '2026-06-15',
        'status' => 'ready',
    ]);

    $this->dayPlan = DayPlan::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60,
    ]);

    // Two planned meals so a skip redistributes onto the remaining one.
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

it('skip marks a pending meal as saltada directly, without completing it first', function () {
    $this->actingAs($this->user)
        ->patchJson("/api/meal-logs/{$this->log1->id}/skip")
        ->assertStatus(200);

    $this->log1->refresh();
    expect($this->log1->saltada)->toBeTrue();
    expect($this->log1->realizada)->toBeFalse();

    // The remaining meal absorbs the skipped calories and a snapshot is stored.
    $this->item2->refresh();
    expect((float) $this->item2->calorias)->toBeGreaterThan(150);

    $this->dailyLog->refresh();
    expect($this->dailyLog->recalculo_snapshot)->not->toBeNull();
});

it('reset restores the quantities redistributed by a skip and clears the recalc state', function () {
    $this->actingAs($this->user)->patchJson("/api/meal-logs/{$this->log1->id}/skip")->assertStatus(200);

    $this->actingAs($this->user)
        ->patchJson("/api/meal-logs/{$this->log1->id}/reset")
        ->assertStatus(200);

    $this->log1->refresh();
    expect($this->log1->saltada)->toBeFalse();
    expect($this->log1->realizada)->toBeFalse();

    $this->item2->refresh();
    expect((float) $this->item2->cantidad_gramos)->toEqualWithDelta(100, 1);
    expect((float) $this->item2->calorias)->toEqualWithDelta(100, 1);

    $this->dailyLog->refresh();
    expect($this->dailyLog->recalculo_motivo)->toBeNull();
    expect($this->dailyLog->recalculo_snapshot)->toBeNull();
});

it('reset reverts a completed meal back to pending and removes its logged items', function () {
    $this->actingAs($this->user)->patchJson("/api/meal-logs/{$this->log1->id}/complete")->assertStatus(200);
    expect($this->log1->fresh()->mealLogItems()->count())->toBeGreaterThan(0);

    $this->actingAs($this->user)
        ->patchJson("/api/meal-logs/{$this->log1->id}/reset")
        ->assertStatus(200);

    $this->log1->refresh();
    expect($this->log1->realizada)->toBeFalse();
    expect($this->log1->saltada)->toBeFalse();
    expect($this->log1->mealLogItems()->count())->toBe(0);
});

it('reset on another users meal log returns HTTP 403', function () {
    $this->actingAs($this->otherUser)
        ->patchJson("/api/meal-logs/{$this->log1->id}/reset")
        ->assertStatus(403);
});
