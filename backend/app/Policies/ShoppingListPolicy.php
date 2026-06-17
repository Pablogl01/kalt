<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

class ShoppingListPolicy
{
    public function view(User $user, ShoppingList $shoppingList): bool
    {
        return $user->id === $shoppingList->weeklyPlan->user_id;
    }

    public function update(User $user, ShoppingList $shoppingList): bool
    {
        return $user->id === $shoppingList->weeklyPlan->user_id;
    }
}
