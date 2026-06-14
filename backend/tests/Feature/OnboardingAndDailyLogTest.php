<?php

use App\Models\User;
use App\Models\Food;
use App\Models\MealLog;
use App\Models\DailyLog;
use App\Models\WeeklyPlan;
use App\Jobs\GenerateDietJob;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    if (Food::count() === 0) {
        Food::create(['nombre' => 'Pollo', 'categoria' => 'proteina', 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2]);
        Food::create(['nombre' => 'Arroz', 'categoria' => 'carbos', 'calorias' => 100, 'proteina' => 2, 'carbos' => 20, 'grasa' => 0]);
        Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas', 'calorias' => 900, 'proteina' => 0, 'carbos' => 0, 'grasa' => 100]);
    }
});

it('step1 calculates and returns correct macros', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/onboarding/step1', [
            'sexo'     => 'hombre',
            'peso'     => 80,
            'altura'   => 180,
            'edad'     => 30,
            'objetivo' => 'volumen',
        ])
        ->assertStatus(200)
        ->assertJsonStructure(['user', 'macros' => ['calorias', 'proteina', 'carbos', 'grasa']]);
});

it('step2 creates meal slots and dispatches GenerateDietJob', function () {
    Queue::fake();
    $user = User::factory()->create([
        'sexo'     => 'hombre',
        'peso'     => 80,
        'altura'   => 180,
        'edad'     => 30,
        'objetivo' => 'volumen',
    ]);

    $this->actingAs($user)
        ->postJson('/api/onboarding/step2', [
            'nivel_actividad' => 'moderado',
            'dias_entreno'    => [1, 3, 5],
            'num_comidas'     => 3,
            'hora_gimnasio'   => '18:00:00',
        ])
        ->assertStatus(202);

    $user->refresh();
    $template = $user->mealTemplates()->first();
    expect($template)->not->toBeNull();
    expect($template->num_comidas)->toBe(3);
    expect($template->mealSlots)->toHaveCount(3);

    Queue::assertPushed(GenerateDietJob::class);
});

it('step3 stores restrictions', function () {
    Queue::fake();
    $user = User::factory()->create([
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'nivel_actividad' => 'moderado',
        'objetivo'        => 'volumen',
    ]);

    $food = Food::first();

    $this->actingAs($user)
        ->postJson('/api/onboarding/step3', [
            'restricciones' => [
                ['food_id' => $food->id, 'tipo' => 'alergia']
            ]
        ])
        ->assertStatus(202);

    $user->refresh();
    expect($user->foodRestrictions)->toHaveCount(1);
    expect($user->foodRestrictions->first()->food_id)->toBe($food->id);
    expect($user->foodRestrictions->first()->tipo)->toBe('alergia');
});

it('creates daily log if not exists and loads meal logs', function () {
    $user = User::factory()->create([
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'nivel_actividad' => 'moderado',
        'objetivo'        => 'volumen',
    ]);

    // Setup template and meal slots
    $template = $user->mealTemplates()->create(['num_comidas' => 3]);
    $template->mealSlots()->create(['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00']);
    $template->mealSlots()->create(['orden' => 2, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00']);

    $fecha = '2026-06-14';

    $this->actingAs($user)
        ->getJson("/api/daily-logs/{$fecha}")
        ->assertStatus(200)
        ->assertJsonStructure(['id', 'fecha', 'meal_logs']);

    $dailyLog = DailyLog::where('user_id', $user->id)->whereDate('fecha', $fecha)->first();
    expect($dailyLog)->not->toBeNull();
    expect($dailyLog->mealLogs)->toHaveCount(2);
});

it('forbids access to other users daily log', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $log = DailyLog::create([
        'user_id'             => $owner->id,
        'fecha'               => '2026-06-14',
        'entreno_planificado' => true,
        'ha_entrenado'        => true,
    ]);

    $this->actingAs($other)
        ->getJson("/api/daily-logs/{$log->id}")
        ->assertStatus(403);
});

it('marks meal log as completed', function () {
    $user = User::factory()->create();
    $dailyLog = DailyLog::create([
        'user_id'             => $user->id,
        'fecha'               => '2026-06-14',
        'entreno_planificado' => true,
        'ha_entrenado'        => true,
    ]);

    $mealLog = MealLog::create([
        'daily_log_id' => $dailyLog->id,
        'es_extra'     => false,
        'realizada'    => false,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/meal-logs/{$mealLog->id}/complete")
        ->assertStatus(200)
        ->assertJsonPath('realizada', true);
});

it('forbids other users to complete meal log', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $dailyLog = DailyLog::create([
        'user_id'             => $owner->id,
        'fecha'               => '2026-06-14',
        'entreno_planificado' => true,
        'ha_entrenado'        => true,
    ]);

    $mealLog = MealLog::create([
        'daily_log_id' => $dailyLog->id,
        'es_extra'     => false,
        'realizada'    => false,
    ]);

    $this->actingAs($other)
        ->patchJson("/api/meal-logs/{$mealLog->id}/complete")
        ->assertStatus(403);
});

it('updates daily log training state and recalculation motive', function () {
    $user = User::factory()->create();
    $dailyLog = DailyLog::create([
        'user_id'             => $user->id,
        'fecha'               => '2026-06-14',
        'entreno_planificado' => true,
        'ha_entrenado'        => true,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/daily-logs/{$dailyLog->id}/training", [
            'ha_entrenado'  => false,
            'hora_gimnasio' => null,
        ])
        ->assertStatus(200)
        ->assertJsonPath('ha_entrenado', false)
        ->assertJsonPath('recalculo_motivo', 'entreno_no_realizado');
});

it('adds extra meal', function () {
    $user = User::factory()->create();
    $dailyLog = DailyLog::create([
        'user_id'             => $user->id,
        'fecha'               => '2026-06-14',
        'entreno_planificado' => true,
        'ha_entrenado'        => true,
    ]);

    $food = Food::first();

    $this->actingAs($user)
        ->postJson("/api/meal-logs/{$dailyLog->id}/extra", [
            'items' => [
                ['food_id' => $food->id, 'cantidad_gramos' => 150]
            ]
        ])
        ->assertStatus(201)
        ->assertJsonPath('es_extra', true);
});
