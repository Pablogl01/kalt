<?php

use App\Models\User;
use App\Models\Food;
use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\MealLogItem;
use App\Models\UserWeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->food = Food::create([
        'nombre'    => 'Arroz',
        'categoria' => 'carbos',
        'calorias'  => 130,
        'proteina'  => 2.7,
        'carbos'    => 28,
        'grasa'     => 0.3
    ]);
});

it('POST /api/weight-logs sin autenticacion devuelve HTTP 401', function () {
    $response = $this->postJson('/api/weight-logs', [
        'peso' => 75.5,
        'fecha' => '2026-06-17',
    ]);

    $response->assertStatus(401);
});

it('POST /api/weight-logs con peso invalido devuelve HTTP 422', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/weight-logs', [
            'peso' => 'invalid-weight',
            'fecha' => '2026-06-17',
        ]);

    $response->assertStatus(422);

    $response2 = $this->actingAs($this->user)
        ->postJson('/api/weight-logs', [
            'peso' => -5,
            'fecha' => '2026-06-17',
        ]);

    $response2->assertStatus(422);
});

it('GET /api/stats/macros sin autenticacion devuelve HTTP 401', function () {
    $response = $this->getJson('/api/stats/macros');

    $response->assertStatus(401);
});

it('GET /api/stats/macros con rango de 400 dias devuelve HTTP 422', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/stats/macros?from=2025-01-01&to=2026-02-15');

    $response->assertStatus(422);
});

it('GET /api/stats/adherence devuelve el nivel correcto para dias con datos conocidos', function () {
    $log = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-17',
        'entreno_planificado' => true,
        'ha_entrenado' => true,
    ]);

    MealLog::create([
        'daily_log_id' => $log->id,
        'es_extra' => false,
        'realizada' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/stats/adherence?from=2026-06-17&to=2026-06-17');

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'fecha' => '2026-06-17',
        'nivel' => 'completa',
    ]);
});

it('la cache de estadisticas se invalida al registrar un nuevo MealLogItem', function () {
    $log = DailyLog::create([
        'user_id' => $this->user->id,
        'fecha' => '2026-06-17',
        'entreno_planificado' => false,
        'ha_entrenado' => false,
    ]);

    $mealLog = MealLog::create([
        'daily_log_id' => $log->id,
        'es_extra' => false,
        'realizada' => true,
    ]);

    // Warm cache
    $this->actingAs($this->user)
        ->getJson('/api/stats/macros?from=2026-06-17&to=2026-06-17');

    $cacheKey = "stats:{$this->user->id}:macros:2026-06-17:2026-06-17";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Create item -> should invalidate cache
    MealLogItem::create([
        'meal_log_id' => $mealLog->id,
        'food_id' => $this->food->id,
        'cantidad_gramos' => 100,
        'calorias' => 130,
        'proteina' => 2.7,
        'carbos' => 28,
        'grasa' => 0.3
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});
