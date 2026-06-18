<?php

use App\Models\User;
use App\Models\Food;
use App\Models\FoodSubstitute;
use App\Models\WeeklyPlan;
use App\Models\DayPlan;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\ShoppingList;
use App\Models\ShoppingItem;
use App\Services\ShoppingGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'sexo' => 'hombre', 'peso' => 80.0, 'altura' => 180.0, 'edad' => 25,
        'nivel_actividad' => 'moderado', 'objetivo' => 'volumen',
    ]);
    $this->otherUser = User::factory()->create();

    $this->pollo = Food::create(['nombre' => 'Pollo', 'categoria' => 'proteina', 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2]);
    $this->pavo  = Food::create(['nombre' => 'Pavo', 'categoria' => 'proteina', 'calorias' => 110, 'proteina' => 22, 'carbos' => 0, 'grasa' => 1.5]);
    $this->arroz = Food::create(['nombre' => 'Arroz', 'categoria' => 'carbos', 'calorias' => 130, 'proteina' => 2.7, 'carbos' => 28, 'grasa' => 0.3]);

    FoodSubstitute::create(['food_id' => $this->pollo->id, 'substitute_food_id' => $this->pavo->id, 'similitud_macros' => 95.0]);

    $this->weeklyPlan = WeeklyPlan::create(['user_id' => $this->user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $this->dayPlan = DayPlan::create(['weekly_plan_id' => $this->weeklyPlan->id, 'fecha' => '2026-06-15', 'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60]);
    $this->meal = Meal::create(['day_plan_id' => $this->dayPlan->id, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00']);

    $this->itemPollo = MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $this->pollo->id, 'cantidad_gramos' => 100, 'calorias' => 100, 'proteina' => 20, 'carbos' => 0, 'grasa' => 2]);
    $this->itemArroz = MealItem::create(['meal_id' => $this->meal->id, 'food_id' => $this->arroz->id, 'cantidad_gramos' => 100, 'calorias' => 130, 'proteina' => 2.7, 'carbos' => 28, 'grasa' => 0.3]);
});

it('manual regeneration preserves tengo_en_casa for foods still in the list', function () {
    $gen = new ShoppingGenerator();
    $gen->generate($this->weeklyPlan)
        ->shoppingItems()->where('food_id', $this->pollo->id)->update(['tengo_en_casa' => true]);

    $newList = $gen->generate($this->weeklyPlan->fresh());

    expect($newList->shoppingItems->firstWhere('food_id', $this->pollo->id)->tengo_en_casa)->toBeTrue();
});

it('does not resurrect a marked food that is no longer in the plan', function () {
    $list = ShoppingList::create(['weekly_plan_id' => $this->weeklyPlan->id, 'generada_en' => now()]);
    // Pavo is marked but is not part of the plan.
    ShoppingItem::create([
        'shopping_list_id' => $list->id, 'food_id' => $this->pavo->id,
        'cantidad_total' => 50, 'categoria' => 'proteina', 'tengo_en_casa' => true,
    ]);

    $newList = (new ShoppingGenerator())->generate($this->weeklyPlan->fresh());

    expect($newList->shoppingItems->pluck('food_id')->all())->not->toContain($this->pavo->id);
    expect($newList->shoppingItems)->toHaveCount(2); // Pollo + Arroz only
});

it('automatic regeneration after a plan substitution preserves marks of remaining foods', function () {
    (new ShoppingGenerator())->generate($this->weeklyPlan)
        ->shoppingItems()->where('food_id', $this->arroz->id)->update(['tengo_en_casa' => true]);

    // Substituting Pollo -> Pavo in the plan triggers automatic regeneration.
    $this->actingAs($this->user)
        ->patchJson("/api/meal-items/{$this->itemPollo->id}/substitute", ['substitute_food_id' => $this->pavo->id])
        ->assertStatus(200);

    $newList = $this->weeklyPlan->fresh()->shoppingList;
    expect($newList->shoppingItems->firstWhere('food_id', $this->arroz->id)->tengo_en_casa)->toBeTrue();
});

it('shopping list of another user is forbidden (HTTP 403)', function () {
    $list = (new ShoppingGenerator())->generate($this->weeklyPlan);

    $this->actingAs($this->otherUser)
        ->getJson("/api/shopping-lists/{$list->id}")
        ->assertStatus(403);
});
