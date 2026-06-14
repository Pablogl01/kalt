<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Services\DietCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly DietCalculator $calculator) {}

    /**
     * Return the authenticated user's profile with calculated macros.
     *
     * The `macros` key is null when the user has not yet completed
     * all fields required for the calculation (sexo, peso, altura,
     * edad, nivel_actividad, objetivo).
     */
    public function show(Request $request): JsonResponse
    {
        $user  = $request->user();
        $macros = $this->calculator->userHasCompleteProfile($user)
            ? $this->calculator->calculate($user)
            : null;

        return response()->json([
            'user'   => $user,
            'macros' => $macros,
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * Returns the freshly loaded user and recalculated macros after update.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());
        $user->refresh();

        $macros = $this->calculator->userHasCompleteProfile($user)
            ? $this->calculator->calculate($user)
            : null;

        return response()->json([
            'user'   => $user,
            'macros' => $macros,
        ]);
    }
}
