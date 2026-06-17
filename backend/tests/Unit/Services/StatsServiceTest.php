<?php

use App\Models\User;
use App\Models\Food;
use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\MealLogItem;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\UserWeightLog;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->statsService = new StatsService();
    $this->user = User::factory()->create();

    $this->food = Food::create([
        'nombre'    => 'Pollo',
        'categoria' => 'proteina',
        'calorias'  => 100,
        'proteina'  => 20,
        'carbos'    => 0,
        'grasa'     => 2
    ]);
});

it('getMacroStats devuelve calorias reales correctas para un rango con datos conocidos', function () {
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-03');

    $weeklyPlan = WeeklyPlan::create([
        'user_id' => $this->user->id,
        'semana_inicio' => '2026-06-01',
        'status' => 'ready'
    ]);

    DayPlan::create([
        'weekly_plan_id' => $weeklyPlan->id,
        'fecha' => '2026-06-01',
        'calorias_objetivo' => 2000,
        'proteina_obj' => 150,
        'carbos_obj' => 200,
        'grasa_obj' => 60
    ]);

    $dailyLog = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-01',
        'entreno_planificado' => false,
        'ha_entrenado' => false
    ]);

    $mealLog = MealLog::create([
        'daily_log_id' => $dailyLog->id,
        'es_extra' => false,
        'realizada' => true
    ]);

    MealLogItem::create([
        'meal_log_id' => $mealLog->id,
        'food_id' => $this->food->id,
        'cantidad_gramos' => 100,
        'calorias' => 150,
        'proteina' => 30,
        'carbos' => 10,
        'grasa' => 2
    ]);

    $stats = $this->statsService->getMacroStats($this->user, $from, $to);

    expect($stats)->toHaveCount(3);
    expect($stats[0]['fecha'])->toBe('2026-06-01');
    expect($stats[0]['calorias_reales'])->toEqual(150.0);
    expect($stats[0]['calorias_objetivo'])->toEqual(2000.0);

    expect($stats[1]['fecha'])->toBe('2026-06-02');
    expect($stats[1]['calorias_reales'])->toEqual(0.0);
    expect($stats[1]['calorias_objetivo'])->toEqual(0.0);
});

it('getAdherenceStats clasifica correctamente un dia como completa, parcial y sin_datos', function () {
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-03');

    // 2026-06-01: completa
    $log1 = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-01',
        'entreno_planificado' => true,
        'ha_entrenado' => true
    ]);
    MealLog::create([
        'daily_log_id' => $log1->id,
        'es_extra' => false,
        'realizada' => true
    ]);

    // 2026-06-02: parcial
    $log2 = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-02',
        'entreno_planificado' => true,
        'ha_entrenado' => false // Missed workout
    ]);
    MealLog::create([
        'daily_log_id' => $log2->id,
        'es_extra' => false,
        'realizada' => true
    ]);

    // 2026-06-03: sin_datos (no log)

    $stats = $this->statsService->getAdherenceStats($this->user, $from, $to);

    expect($stats)->toHaveCount(3);
    expect($stats[0]['fecha'])->toBe('2026-06-01');
    expect($stats[0]['nivel'])->toBe('completa');

    expect($stats[1]['fecha'])->toBe('2026-06-02');
    expect($stats[1]['nivel'])->toBe('parcial');

    expect($stats[2]['fecha'])->toBe('2026-06-03');
    expect($stats[2]['nivel'])->toBe('sin_datos');
});

it('getTrainingStats calcula correctamente racha_actual con dias consecutivos entrenados', function () {
    $from = Carbon::now()->subDays(10);
    $to = Carbon::now();

    // Trained yesterday and today
    DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => Carbon::yesterday()->format('Y-m-d'),
        'entreno_planificado' => true,
        'ha_entrenado' => true
    ]);

    DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => Carbon::today()->format('Y-m-d'),
        'entreno_planificado' => true,
        'ha_entrenado' => true
    ]);

    $stats = $this->statsService->getTrainingStats($this->user, $from, $to);

    expect($stats['racha_actual'])->toBe(2);
});

it('getTrainingStats calcula correctamente mejor_racha historica', function () {
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-15');

    // Racha 3 days: 01, 02, 03
    for ($i = 1; $i <= 3; $i++) {
        DailyLog::create([
            'user_id' => $this->user->id,
            'fecha' => "2026-06-0{$i}",
            'entreno_planificado' => true,
            'ha_entrenado' => true
        ]);
    }

    // Gap on 04

    // Racha 4 days: 05, 06, 07, 08
    for ($i = 5; $i <= 8; $i++) {
        DailyLog::create([
            'user_id' => $this->user->id,
            'fecha' => "2026-06-0{$i}",
            'entreno_planificado' => true,
            'ha_entrenado' => true
        ]);
    }

    $stats = $this->statsService->getTrainingStats($this->user, $from, $to);

    expect($stats['mejor_racha'])->toBe(4);
});

it('getWeightStats respeta el rango de fechas y no devuelve registros fuera de el', function () {
    $from = Carbon::parse('2026-06-05');
    $to = Carbon::parse('2026-06-10');

    // Weight logs
    UserWeightLog::create(['user_id' => $this->user->id, 'fecha' => '2026-06-04', 'peso' => 80.0]);
    UserWeightLog::create(['user_id' => $this->user->id, 'fecha' => '2026-06-06', 'peso' => 79.5]);
    UserWeightLog::create(['user_id' => $this->user->id, 'fecha' => '2026-06-10', 'peso' => 79.0]);
    UserWeightLog::create(['user_id' => $this->user->id, 'fecha' => '2026-06-11', 'peso' => 78.5]);

    $stats = $this->statsService->getWeightStats($this->user, $from, $to);

    expect($stats)->toHaveCount(2);
    expect($stats[0]['fecha'])->toBe('2026-06-06');
    expect($stats[1]['fecha'])->toBe('2026-06-10');
});

it('rango superior a 365 dias es rechazado con excepcion descriptiva', function () {
    $from = Carbon::parse('2025-06-01');
    $to = Carbon::parse('2026-06-15');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('El rango de fechas no puede ser superior a 365 días.');

    $this->statsService->getMacroStats($this->user, $from, $to);
});
