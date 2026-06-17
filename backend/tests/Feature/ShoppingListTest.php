<?php

use App\Models\User;
use App\Models\Food;
use App\Models\FoodSubstitute;
use App\Models\UserFoodRestriction;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\ShoppingList;
use App\Models\ShoppingItem;
use App\Jobs\GenerateDietJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'sexo' => 'hombre',
        'peso' => 80.0,
        'altura' => 180.0,
        'edad' => 25,
        'nivel_actividad' => 'moderado',
        'objetivo' => 'volumen'
    ]);
    $this->otherUser = User::factory()->create([
        'sexo' => 'hombre',
        'peso' => 80.0,
        'altura' => 180.0,
        'edad' => 25,
        'nivel_actividad' => 'moderado',
        'objetivo' => 'volumen'
    ]);

    $this->food = Food::create([
        'nombre' => 'Pollo',
        'categoria' => 'proteina',
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);

    $this->substituteFood = Food::create([
        'nombre' => 'Pavo',
        'categoria' => 'proteina',
        'calorias' => 110,
        'proteina' => 22,
        'carbos' => 0,
        'grasa' => 1.5
    ]);

    FoodSubstitute::create([
        'food_id' => $this->food->id,
        'substitute_food_id' => $this->substituteFood->id,
        'similitud_macros' => 95.0
    ]);

    Food::create([
        'nombre' => 'Arroz',
        'categoria' => 'carbos',
        'calorias' => 130,
        'proteina' => 2.7,
        'carbos' => 28,
        'grasa' => 0.3
    ]);

    Food::create([
        'nombre' => 'Aceite de oliva',
        'categoria' => 'grasas',
        'calorias' => 900,
        'proteina' => 0,
        'carbos' => 0,
        'grasa' => 100
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

    $this->meal = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00']);

    $this->mealItem = MealItem::create([
        'meal_id' => $this->meal->id,
        'food_id' => $this->food->id,
        'cantidad_gramos' => 100,
        'calorias' => 100,
        'proteina' => 20,
        'carbos' => 0,
        'grasa' => 2
    ]);
});

it('GET /api/shopping-lists/active without authentication returns HTTP 401', function () {
    $this->getJson('/api/shopping-lists/active')
        ->assertStatus(401);
});

it('GET /api/shopping-lists/active of another users list returns HTTP 403', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);

    $this->actingAs($this->otherUser)
        ->getJson("/api/shopping-lists/active")
        ->assertStatus(404); // returns 404 since otherUser has no active plan

    // Try showing the specific list directly
    $this->actingAs($this->otherUser)
        ->getJson("/api/shopping-lists/{$shoppingList->id}")
        ->assertStatus(403); // ShoppingListPolicy forbids viewing other user's list
});

it('POST /api/shopping-lists/generate generates the list and returns HTTP 201', function () {
    $this->actingAs($this->user)
        ->postJson('/api/shopping-lists/generate')
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'shopping_items']);

    expect(ShoppingList::count())->toBe(1);
    expect(ShoppingItem::count())->toBe(1);
});

it('PATCH /api/shopping-items/{id}/have marks the item correctly', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);
    $item = ShoppingItem::create([
        'shopping_list_id' => $shoppingList->id,
        'food_id' => $this->food->id,
        'cantidad_total' => 100.0,
        'categoria' => 'proteina',
        'tengo_en_casa' => false
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/shopping-items/{$item->id}/have")
        ->assertStatus(200)
        ->assertJson([
            'id' => $item->id,
            'tengo_en_casa' => true
        ]);

    expect($item->fresh()->tengo_en_casa)->toBeTrue();
});

it('PATCH /api/shopping-items/{id}/dont-want returns valid substitutes (no user allergens)', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);
    $item = ShoppingItem::create([
        'shopping_list_id' => $shoppingList->id,
        'food_id' => $this->food->id,
        'cantidad_total' => 100.0,
        'categoria' => 'proteina',
        'no_lo_quiero' => false
    ]);

    // Test normal return of substitutes
    $this->actingAs($this->user)
        ->patchJson("/api/shopping-items/{$item->id}/dont-want")
        ->assertStatus(200)
        ->assertJsonCount(1); // Only Pavo is a substitute

    expect($item->fresh()->no_lo_quiero)->toBeTrue();

    // Now mark Pavo as allergy
    UserFoodRestriction::create([
        'user_id' => $this->user->id,
        'food_id' => $this->substituteFood->id,
        'tipo' => 'alergia'
    ]);

    // Should return 0 substitutes because the only substitute is an allergen
    $this->actingAs($this->user)
        ->patchJson("/api/shopping-items/{$item->id}/dont-want")
        ->assertStatus(200)
        ->assertJsonCount(0);
});

it('PATCH /api/shopping-items/{id}/substitute with allergen returns HTTP 422', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);
    $item = ShoppingItem::create([
        'shopping_list_id' => $shoppingList->id,
        'food_id' => $this->food->id,
        'cantidad_total' => 100.0,
        'categoria' => 'proteina'
    ]);

    UserFoodRestriction::create([
        'user_id' => $this->user->id,
        'food_id' => $this->substituteFood->id,
        'tipo' => 'alergia'
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/shopping-items/{$item->id}/substitute", [
            'substitute_food_id' => $this->substituteFood->id
        ])
        ->assertStatus(422);
});

it('PATCH /api/shopping-items/{id}/substitute valid updates sustituido_por_food_id and contains cantidad_recalculada', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);
    $item = ShoppingItem::create([
        'shopping_list_id' => $shoppingList->id,
        'food_id' => $this->food->id,
        'cantidad_total' => 100.0, // 100g of Pollo (100 kcal)
        'categoria' => 'proteina'
    ]);

    // Substitute with Pavo (110 kcal)
    // 100g * 100 / 110 = 90.91g
    $this->actingAs($this->user)
        ->patchJson("/api/shopping-items/{$item->id}/substitute", [
            'substitute_food_id' => $this->substituteFood->id
        ])
        ->assertStatus(200)
        ->assertJson([
            'sustituido_por_food_id' => $this->substituteFood->id,
            'cantidad_recalculada' => 90.91
        ]);

    expect($item->fresh()->sustituido_por_food_id)->toBe($this->substituteFood->id);
});

it('regenerates ShoppingList automatically when plan is substituted', function () {
    $shoppingList = ShoppingList::create([
        'weekly_plan_id' => $this->weeklyPlan->id,
        'generada_en' => now()
    ]);
    ShoppingItem::create([
        'shopping_list_id' => $shoppingList->id,
        'food_id' => $this->food->id,
        'cantidad_total' => 100.0,
        'categoria' => 'proteina'
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/meal-items/{$this->mealItem->id}/substitute", [
            'substitute_food_id' => $this->substituteFood->id
        ])
        ->assertStatus(200);

    // After plan substitute, the shopping list should be automatically regenerated.
    // The items will now list Pavo instead of Pollo because the plan changed.
    $list = $this->weeklyPlan->fresh()->shoppingList;
    expect($list->shoppingItems)->toHaveCount(1);
    expect($list->shoppingItems->first()->food_id)->toBe($this->substituteFood->id);
});

it('creates ShoppingList automatically when GenerateDietJob finishes successfully', function () {
    // Generate default template etc. to make GenerateDietJob run successfully
    $template = $this->user->mealTemplates()->create([
        'num_comidas' => 3,
        'es_personalizado' => false
    ]);
    $template->mealSlots()->create(['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false]);
    $template->mealSlots()->create(['orden' => 2, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00', 'es_pre_entreno' => true, 'es_post_entreno' => false]);
    $template->mealSlots()->create(['orden' => 3, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => true]);

    $plan = WeeklyPlan::create([
        'user_id' => $this->user->id,
        'semana_inicio' => '2026-06-22',
        'status' => 'pending'
    ]);

    $job = new GenerateDietJob($plan);
    $job->handle();

    expect($plan->fresh()->status)->toBe('ready');
    
    // Automatically created ShoppingList
    $list = $plan->fresh()->shoppingList;
    expect($list)->not->toBeNull();
    expect($list->shoppingItems->count())->toBeGreaterThan(0);
});
