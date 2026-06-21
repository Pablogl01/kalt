<?php

use App\Models\DayPlan;
use App\Models\Food;
use App\Models\Meal;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\MealPlateGenerator;

it('breakfast slots only use breakfast-appropriate protein and carbs', function () {
    // Breakfast-appropriate pair + a savory pair that must never land at breakfast.
    Food::create(['nombre' => 'Huevos', 'categoria' => 'proteina', 'calorias' => 155, 'proteina' => 13, 'carbos' => 1, 'grasa' => 11, 'apto_desayuno' => true]);
    Food::create(['nombre' => 'Avena', 'categoria' => 'carbos', 'calorias' => 389, 'proteina' => 17, 'carbos' => 66, 'grasa' => 7, 'apto_desayuno' => true]);
    Food::create(['nombre' => 'Bacalao', 'categoria' => 'proteina', 'calorias' => 82, 'proteina' => 18, 'carbos' => 0, 'grasa' => 1, 'apto_desayuno' => false]);
    Food::create(['nombre' => 'Yuca', 'categoria' => 'carbos', 'calorias' => 160, 'proteina' => 1, 'carbos' => 38, 'grasa' => 0, 'apto_desayuno' => false]);
    Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas', 'calorias' => 884, 'proteina' => 0, 'carbos' => 0, 'grasa' => 100]);

    $user = User::factory()->create();
    $weeklyPlan = WeeklyPlan::create(['user_id' => $user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $dayPlan = DayPlan::create([
        'weekly_plan_id' => $weeklyPlan->id, 'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60,
    ]);
    $meal = Meal::create(['day_plan_id' => $dayPlan->id, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00']);

    // Roll a few times: random picks must never reach the savory foods at breakfast.
    foreach (range(1, 10) as $_) {
        $meal->mealItems()->delete();
        app(MealPlateGenerator::class)->build($meal, ['proteina' => 30, 'carbos' => 60, 'grasa' => 15], $user);

        $names = $meal->mealItems()->with('food')->get()->pluck('food.nombre');
        expect($names)->not->toContain('Bacalao');
        expect($names)->not->toContain('Yuca');
    }
});

it('main meals exclude breakfast/snack-only foods', function () {
    // A real-meal pair + breakfast/snack-only foods that must not land at lunch.
    Food::create(['nombre' => 'Pollo', 'categoria' => 'proteina', 'calorias' => 165, 'proteina' => 31, 'carbos' => 0, 'grasa' => 4, 'apto_principal' => true]);
    Food::create(['nombre' => 'Arroz', 'categoria' => 'carbos', 'calorias' => 130, 'proteina' => 3, 'carbos' => 28, 'grasa' => 0, 'apto_principal' => true]);
    Food::create(['nombre' => 'Whey', 'categoria' => 'proteina', 'calorias' => 380, 'proteina' => 80, 'carbos' => 5, 'grasa' => 3, 'apto_desayuno' => true, 'apto_principal' => false]);
    Food::create(['nombre' => 'Tortitas', 'categoria' => 'carbos', 'calorias' => 387, 'proteina' => 8, 'carbos' => 82, 'grasa' => 3, 'apto_desayuno' => true, 'apto_principal' => false]);
    Food::create(['nombre' => 'Aceite', 'categoria' => 'grasas', 'calorias' => 884, 'proteina' => 0, 'carbos' => 0, 'grasa' => 100]);

    $user = User::factory()->create();
    $weeklyPlan = WeeklyPlan::create(['user_id' => $user->id, 'semana_inicio' => '2026-06-15', 'status' => 'ready']);
    $dayPlan = DayPlan::create([
        'weekly_plan_id' => $weeklyPlan->id, 'fecha' => '2026-06-15',
        'calorias_objetivo' => 2000, 'proteina_obj' => 150, 'carbos_obj' => 200, 'grasa_obj' => 60,
    ]);
    $meal = Meal::create(['day_plan_id' => $dayPlan->id, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00']);

    foreach (range(1, 10) as $_) {
        $meal->mealItems()->delete();
        app(MealPlateGenerator::class)->build($meal, ['proteina' => 40, 'carbos' => 70, 'grasa' => 15], $user);

        $names = $meal->mealItems()->with('food')->get()->pluck('food.nombre');
        expect($names)->not->toContain('Whey');
        expect($names)->not->toContain('Tortitas');
    }
});
