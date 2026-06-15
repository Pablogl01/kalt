<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MealItem;

class MealItemPolicy
{
    public function view(User $user, MealItem $mealItem): bool
    {
        return $user->id === $mealItem->meal->dayPlan->weeklyPlan->user_id;
    }

    public function update(User $user, MealItem $mealItem): bool
    {
        return $user->id === $mealItem->meal->dayPlan->weeklyPlan->user_id;
    }
}
