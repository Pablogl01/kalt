<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Jobs\GenerateDietJob;
use App\Models\WeeklyPlan;
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
        $user  = $request->user()->load('foodRestrictions', 'supplements.food');
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

        // Snapshot training days (sorted) to detect a change after the update.
        $daysBefore = is_array($user->dias_entreno) ? $user->dias_entreno : [];
        sort($daysBefore);

        $user->update($request->validated());
        $user->refresh();
        $user->load('foodRestrictions', 'supplements.food');

        $daysAfter = is_array($user->dias_entreno) ? $user->dias_entreno : [];
        sort($daysAfter);

        $macros = $this->calculator->userHasCompleteProfile($user)
            ? $this->calculator->calculate($user)
            : null;

        $response = ['user' => $user, 'macros' => $macros];

        // Changing the training days reshapes the week, so regenerate the plan.
        if ($daysBefore !== $daysAfter && $this->calculator->userHasCompleteProfile($user)) {
            $plan = WeeklyPlan::create([
                'user_id'       => $user->id,
                'semana_inicio' => now()->startOfWeek()->toDateString(),
                'status'        => 'pending',
            ]);
            GenerateDietJob::dispatch($plan);
            $response['weekly_plan_id'] = $plan->id;
        }

        return response()->json($response);
    }
}
