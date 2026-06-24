<?php

use App\Jobs\GenerateDietJob;
use App\Models\Food;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Profile complete enough for plan generation (peso is encrypted).
    $this->user = User::factory()->create([
        'sexo' => 'hombre',
        'peso' => 80,
        'altura' => 180,
        'edad' => 30,
        'objetivo' => 'volumen',
        'nivel_actividad' => 'moderado',
    ]);

    $this->whey = Food::create([
        'nombre' => 'Proteína de suero en polvo (Whey)',
        'categoria' => 'proteina',
        'calorias' => 380, 'proteina' => 80, 'carbos' => 5, 'grasa' => 3,
    ]);

    $this->creatina = Food::create([
        'nombre' => 'Creatina monohidrato',
        'categoria' => 'suplemento',
        'calorias' => 0, 'proteina' => 0, 'carbos' => 0, 'grasa' => 0,
    ]);
});

it('catalog returns whey and dedicated supplement foods', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/supplements/catalog')
        ->assertStatus(200);

    $names = collect($response->json())->pluck('nombre');
    expect($names)->toContain('Proteína de suero en polvo (Whey)');
    expect($names)->toContain('Creatina monohidrato');
});

it('stores supplements deriving afecta_macros from food macros and dispatches a regeneration', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->putJson('/api/supplements', [
            'supplements' => [
                ['food_id' => $this->whey->id,      'dosis_gramos' => 30, 'momento' => 'post_entreno'],
                ['food_id' => $this->creatina->id,  'dosis_gramos' => 5,  'momento' => 'diario'],
            ],
        ])
        ->assertStatus(200)
        ->assertJsonStructure(['supplements', 'weekly_plan_id']);

    $this->assertDatabaseHas('user_supplements', [
        'user_id' => $this->user->id,
        'food_id' => $this->whey->id,
        'afecta_macros' => 1,
    ]);
    $this->assertDatabaseHas('user_supplements', [
        'user_id' => $this->user->id,
        'food_id' => $this->creatina->id,
        'afecta_macros' => 0,
    ]);

    Queue::assertPushed(GenerateDietJob::class);
});

it('replaces the supplement set on update', function () {
    Queue::fake();

    // First set: whey only.
    $this->actingAs($this->user)->putJson('/api/supplements', [
        'supplements' => [['food_id' => $this->whey->id, 'dosis_gramos' => 30, 'momento' => 'post_entreno']],
    ])->assertStatus(200);

    // Second set: empty — clears everything.
    $this->actingAs($this->user)->putJson('/api/supplements', ['supplements' => []])
        ->assertStatus(200);

    $this->actingAs($this->user)
        ->getJson('/api/supplements')
        ->assertStatus(200)
        ->assertJsonCount(0);
});

it('rejects an invalid momento', function () {
    $this->actingAs($this->user)
        ->putJson('/api/supplements', [
            'supplements' => [['food_id' => $this->whey->id, 'dosis_gramos' => 30, 'momento' => 'nope']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supplements.0.momento']);
});
