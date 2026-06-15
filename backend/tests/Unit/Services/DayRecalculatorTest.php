<?php

use App\Models\User;
use App\Models\Food;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\DailyLog;
use App\Models\MealLog;
use App\Services\DayRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->recalculator = new DayRecalculator();

    $this->user = User::factory()->create([
        'nivel_actividad' => 'moderado',
        'objetivo' => 'volumen'
    ]);

    // Create a food to use
    $this->food = Food::create([
        'nombre' => 'Test Food',
        'categoria' => 'proteina',
        'calorias' => 100,
        'proteina' => 10,
        'carbos' => 10,
        'grasa' => 2
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

    $this->meal1 = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00']);
    $this->meal2 = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00']);
    $this->meal3 = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00']);

    $this->item1 = MealItem::create(['meal_id' => $this->meal1->id, 'food_id' => $this->food->id, 'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 10, 'carbos' => 10, 'grasa' => 2]);
    $this->item2 = MealItem::create(['meal_id' => $this->meal2->id, 'food_id' => $this->food->id, 'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 10, 'carbos' => 10, 'grasa' => 2]);
    $this->item3 = MealItem::create(['meal_id' => $this->meal3->id, 'food_id' => $this->food->id, 'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 10, 'carbos' => 10, 'grasa' => 2]);

    $this->dailyLog = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-15',
        'entreno_planificado' => true,
        'ha_entrenado' => true,
    ]);

    $this->log1 = MealLog::create(['daily_log_id' => $this->dailyLog->id, 'meal_id' => $this->meal1->id, 'es_extra' => false, 'realizada' => false]);
    $this->log2 = MealLog::create(['daily_log_id' => $this->dailyLog->id, 'meal_id' => $this->meal2->id, 'es_extra' => false, 'realizada' => false]);
    $this->log3 = MealLog::create(['daily_log_id' => $this->dailyLog->id, 'meal_id' => $this->meal3->id, 'es_extra' => false, 'realizada' => false]);
});

it('Caso A: redistributes skipped meal macros proportionally among remaining meals', function () {
    // Skip meal1
    $this->log1->update(['realizada' => false]);

    $this->recalculator->recalculateSkippedMeal($this->dailyLog, $this->log1);

    expect($this->dailyLog->fresh()->recalculo_motivo)->toBe('comida_saltada');

    // The remaining meals (meal2, meal3) should have increased in calories
    $this->item2->refresh();
    $this->item3->refresh();

    // Since they were both 100 calories originally, they should divide the 100 skipped calories equally
    // So 100 + 50 = 150 calories each
    expect((float)$this->item2->calorias)->toEqualWithDelta(150.0, 1.0);
    expect((float)$this->item3->calorias)->toEqualWithDelta(150.0, 1.0);

    // Sum of all items in active plan should still match total planned + skipped
    $totalRemaining = $this->item2->calorias + $this->item3->calorias;
    expect((float)$totalRemaining)->toEqualWithDelta(300.0, 2.0);
});

it('Caso A: with 0 remaining meals does not throw exception and sets status', function () {
    $this->log1->update(['realizada' => true]);
    $this->log2->update(['realizada' => true]);
    $this->log3->update(['realizada' => true]);

    expect(fn() => $this->recalculator->recalculateSkippedMeal($this->dailyLog, $this->log1))
        ->not->toThrow(\Exception::class);

    expect($this->dailyLog->fresh()->recalculo_motivo)->toBe('comida_saltada');
});

it('Caso B: reduces calories mainly from carbs', function () {
    // Let's set some carbs in remaining meals
    $this->item2->update(['carbos' => 80, 'calorias' => 400]); // lots of carbs
    $this->item3->update(['carbos' => 80, 'calorias' => 400]);

    $oldDayPlanCal = $this->dayPlan->calorias_objetivo;

    $this->recalculator->recalculateNoTraining($this->dailyLog);

    expect($this->dailyLog->fresh()->recalculo_motivo)->toBe('no_ha_entrenado');

    $this->dayPlan->refresh();
    $newDayPlanCal = $this->dayPlan->calorias_objetivo;

    $reduction = $oldDayPlanCal - $newDayPlanCal;
    expect($reduction)->toBeGreaterThanOrEqual(200.0);
    expect($reduction)->toBeLessThanOrEqual(400.0);

    // Carbs should have been reduced
    $this->item2->refresh();
    $this->item3->refresh();
    expect((float)$this->item2->carbos)->toBeLessThan(80.0);
});

it('Caso C: adds carbs to pre-workout and protein to post-workout closest to gym hour', function () {
    $this->dailyLog->update(['hora_gimnasio' => '17:00:00']);

    // Pre-workout closest: meal2 (14:00)
    // Post-workout closest: meal3 (21:00)
    $this->recalculator->recalculateExtraTraining($this->dailyLog);

    expect($this->dailyLog->fresh()->recalculo_motivo)->toBe('entreno_no_planificado');

    $this->item2->refresh(); // Pre
    $this->item3->refresh(); // Post

    // Pre gets 200 kcal / 50g carbs
    expect($this->item2->carbos)->toBeGreaterThan(10.0);
    // Post gets 200 kcal / 50g protein
    expect($this->item3->proteina)->toBeGreaterThan(10.0);
});

it('Caso C: without gym hour throws exception', function () {
    $this->dailyLog->update(['hora_gimnasio' => null]);

    expect(fn() => $this->recalculator->recalculateExtraTraining($this->dailyLog))
        ->toThrow(\InvalidArgumentException::class);
});

it('Caso D: extra meal subtracts macros correctly from remaining meals', function () {
    // Create extra meal
    $extraLog = MealLog::create([
        'daily_log_id' => $this->dailyLog->id,
        'meal_id' => null,
        'es_extra' => true,
        'realizada' => true,
        'hora_real' => '10:00:00'
    ]);

    $extraLog->mealLogItems()->create([
        'food_id' => $this->food->id,
        'cantidad_gramos' => 50,
        'calorias' => 50,
        'proteina' => 5,
        'carbos' => 5,
        'grasa' => 1
    ]);

    $this->recalculator->recalculateExtraMeal($this->dailyLog, $extraLog);

    expect($this->dailyLog->fresh()->recalculo_motivo)->toBe('comida_extra');

    $this->item2->refresh();
    $this->item3->refresh();

    // The remaining meals should have decreased in calories
    expect((float)$this->item2->calorias)->toBeLessThan(100.0);
    expect((float)$this->item3->calorias)->toBeLessThan(100.0);
});

it('Caso D: if macros at 100%, does not redistribute', function () {
    // Let's make the extra meal huge (equal or larger than target)
    $extraLog = MealLog::create([
        'daily_log_id' => $this->dailyLog->id,
        'meal_id' => null,
        'es_extra' => true,
        'realizada' => true,
        'hora_real' => '10:00:00'
    ]);

    $extraLog->mealLogItems()->create([
        'food_id' => $this->food->id,
        'cantidad_gramos' => 2500, // 2500 kcal (target is 2000)
        'calorias' => 2500,
        'proteina' => 250,
        'carbos' => 250,
        'grasa' => 50
    ]);

    $this->recalculator->recalculateExtraMeal($this->dailyLog, $extraLog);

    $this->item2->refresh();
    $this->item3->refresh();

    // Since we are at > 100%, remaining meals should not change (stay 100 kcal)
    expect((float)$this->item2->calorias)->toEqual(100.0);
    expect((float)$this->item3->calorias)->toEqual(100.0);
});
