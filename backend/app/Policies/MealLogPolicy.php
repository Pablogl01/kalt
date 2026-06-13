<?php

namespace App\Policies;

use App\Models\MealLog;
use App\Models\User;

class MealLogPolicy
{
    public function view(User $user, MealLog $mealLog): bool
    {
        return $user->id === $mealLog->dailyLog->user_id;
    }

    public function update(User $user, MealLog $mealLog): bool
    {
        return $user->id === $mealLog->dailyLog->user_id;
    }

    public function delete(User $user, MealLog $mealLog): bool
    {
        return $user->id === $mealLog->dailyLog->user_id;
    }
}
