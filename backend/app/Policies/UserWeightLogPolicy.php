<?php

namespace App\Policies;

use App\Models\UserWeightLog;
use App\Models\User;

class UserWeightLogPolicy
{
    public function view(User $user, UserWeightLog $userWeightLog): bool
    {
        return $user->id === $userWeightLog->user_id;
    }

    public function update(User $user, UserWeightLog $userWeightLog): bool
    {
        return $user->id === $userWeightLog->user_id;
    }

    public function delete(User $user, UserWeightLog $userWeightLog): bool
    {
        return $user->id === $userWeightLog->user_id;
    }
}
