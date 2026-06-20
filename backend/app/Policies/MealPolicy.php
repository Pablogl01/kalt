<?php

namespace App\Policies;

use App\Models\Meal;
use App\Models\User;

class MealPolicy
{
    public function view(User $user, Meal $meal): bool
    {
        return $user->id === $meal->dayPlan->weeklyPlan->user_id;
    }

    public function update(User $user, Meal $meal): bool
    {
        return $user->id === $meal->dayPlan->weeklyPlan->user_id;
    }
}
