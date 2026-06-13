<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'sexo'              => fake()->randomElement(['hombre', 'mujer']),
            'objetivo'          => fake()->randomElement(['volumen', 'mantenimiento', 'definicion']),
            'nivel_actividad'   => fake()->randomElement(['sedentario', 'ligero', 'moderado', 'alto']),
            'altura'            => fake()->numberBetween(150, 200),
            'edad'              => fake()->numberBetween(18, 60),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
