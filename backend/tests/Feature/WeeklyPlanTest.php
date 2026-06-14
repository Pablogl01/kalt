<?php

use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\Food;
use App\Jobs\GenerateDietJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Fresh seed before every test is handled by fresh migrations when needed, 
    // but here we can just create some foods if needed.
    if (Food::count() === 0) {
        // Ensure there is some food
        Food::create(['nombre' => 'Pollo', 'categoria' => 'proteina', 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2]);
        Food::create(['nombre' => 'Arroz', 'categoria' => 'carbos', 'calorias' => 100, 'proteina' => 2, 'carbos' => 20, 'grasa' => 0]);
        Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas', 'calorias' => 900, 'proteina' => 0, 'carbos' => 0, 'grasa' => 100]);
    }
});

it('returns 401 when trying to generate plan without authentication', function () {
    $this->postJson('/api/plans/generate')
         ->assertStatus(401);
});

it('returns 422 when generating plan with incomplete profile', function () {
    $user = User::factory()->create([
        'sexo' => null, // incomplete profile
    ]);

    $this->actingAs($user)
         ->postJson('/api/plans/generate')
         ->assertStatus(422)
         ->assertJsonStructure(['message']);
});

it('returns 202 and uuid when generating plan with complete profile', function () {
    Queue::fake();

    $user = User::factory()->create([
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'nivel_actividad' => 'moderado',
        'objetivo'        => 'volumen',
    ]);

    $this->actingAs($user)
         ->postJson('/api/plans/generate')
         ->assertStatus(202)
         ->assertJsonStructure(['uuid', 'status']);

    Queue::assertPushed(GenerateDietJob::class);
});

it('returns 403 when accessing another users weekly plan status', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $plan = WeeklyPlan::factory()->create([
        'user_id'       => $owner->id,
        'semana_inicio' => now()->startOfWeek()->toDateString(),
        'status'        => 'pending',
    ]);

    $this->actingAs($other)
         ->getJson("/api/plans/{$plan->id}/status")
         ->assertStatus(403);
});

it('returns 403 when accessing another users weekly plan details', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $plan = WeeklyPlan::factory()->create([
        'user_id'       => $owner->id,
        'semana_inicio' => now()->startOfWeek()->toDateString(),
        'status'        => 'ready',
    ]);

    $this->actingAs($other)
         ->getJson("/api/plans/{$plan->id}")
         ->assertStatus(403);
});

it('updates status to ready when job completes successfully', function () {
    $user = User::factory()->create([
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'nivel_actividad' => 'moderado',
        'objetivo'        => 'volumen',
    ]);

    $plan = WeeklyPlan::create([
        'user_id'       => $user->id,
        'semana_inicio' => now()->startOfWeek()->toDateString(),
        'status'        => 'pending',
    ]);

    $job = new GenerateDietJob($plan);
    $job->handle();

    $plan->refresh();
    expect($plan->status)->toBe('ready');
    expect($plan->dayPlans)->toHaveCount(7);
});

it('updates status to failed when job fails', function () {
    $user = User::factory()->create([
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'nivel_actividad' => 'moderado',
        'objetivo'        => 'volumen',
    ]);

    $plan = WeeklyPlan::create([
        'user_id'       => $user->id,
        'semana_inicio' => now()->startOfWeek()->toDateString(),
        'status'        => 'pending',
    ]);

    // Force failure by deleting all foods temporarily
    Food::truncate();

    $job = new GenerateDietJob($plan);
    try {
        $job->handle();
    } catch (\Throwable $e) {
        // expected
    }

    $plan->refresh();
    expect($plan->status)->toBe('failed');
    expect($plan->error_message)->not->toBeEmpty();
});
