<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Registration
// ──────────────────────────────────────────────

it('registers a new user and returns HTTP 201', function () {
    $response = $this->withSession([])->postJson('/api/register', [
        'name'                  => 'Pablo Test',
        'email'                 => 'pablo@test.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user' => ['id', 'name', 'email']]);

    $this->assertDatabaseHas('users', ['email' => 'pablo@test.com']);
});

it('returns HTTP 422 when registering with a duplicate email', function () {
    User::factory()->create(['email' => 'existing@test.com']);

    $response = $this->withSession([])->postJson('/api/register', [
        'name'                  => 'Another User',
        'email'                 => 'existing@test.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns HTTP 422 when password confirmation does not match', function () {
    $response = $this->withSession([])->postJson('/api/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@test.com',
        'password'              => 'password123',
        'password_confirmation' => 'different456',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

// ──────────────────────────────────────────────
// Login
// ──────────────────────────────────────────────

it('returns HTTP 200 and user data on successful login', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->withSession([])->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'macros',
        ]);
});

it('returns HTTP 422 on incorrect credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correctpassword')]);

    $response = $this->withSession([])->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// ──────────────────────────────────────────────
// Profile — unauthenticated
// ──────────────────────────────────────────────

it('returns HTTP 401 when accessing profile without authentication', function () {
    $this->getJson('/api/profile')->assertStatus(401);
});

// ──────────────────────────────────────────────
// Profile — update and macros
// ──────────────────────────────────────────────

it('updates the profile and returns calculated macros', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/profile', [
        'sexo'            => 'hombre',
        'peso'            => 80,
        'altura'          => 180,
        'edad'            => 30,
        'objetivo'        => 'volumen',
        'nivel_actividad' => 'moderado',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user'   => ['id', 'name', 'email'],
            'macros' => ['calorias', 'proteina', 'carbos', 'grasa'],
        ]);

    // BMR = 1853.36 · TDEE = 1853.36 × 1.55 = 2872.71 · +300 = 3172.71 kcal
    $macros = $response->json('macros');
    expect($macros['calorias'])->toBeGreaterThan(3000.0)
        ->and($macros['proteina'])->toBeGreaterThan(0.0)
        ->and($macros['carbos'])->toBeGreaterThan(0.0)
        ->and($macros['grasa'])->toBeGreaterThan(0.0);
});

it('returns macros as null when profile is incomplete', function () {
    $user = User::factory()->create(); // No physical data

    $response = $this->actingAs($user)->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJson(['macros' => null]);
});
