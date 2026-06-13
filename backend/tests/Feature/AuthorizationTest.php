<?php

use App\Models\User;
use App\Models\WeeklyPlan;

it('returns 403 when accessing another users weekly plan', function () {
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

it('returns 200 when owner accesses their own weekly plan', function () {
    $owner = User::factory()->create();

    $plan = WeeklyPlan::factory()->create([
        'user_id'       => $owner->id,
        'semana_inicio' => now()->startOfWeek()->toDateString(),
        'status'        => 'ready',
    ]);

    $this->actingAs($owner)
         ->getJson("/api/plans/{$plan->id}")
         ->assertStatus(200);
});

it('returns 401 when accessing protected endpoint without auth', function () {
    $this->getJson('/api/user')
         ->assertStatus(401);
});
