<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeeklyPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'semana_inicio' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'generado_en'   => now(),
            'status'        => fake()->randomElement(['pending', 'ready', 'failed']),
        ];
    }
}
