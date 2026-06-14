<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDietJob;
use App\Models\WeeklyPlan;
use App\Services\DietCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DietController extends Controller
{
    /**
     * Dispatch plan generation job (async).
     * Returns HTTP 202 Accepted immediately.
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        $calculator = new DietCalculator();

        if (!$calculator->userHasCompleteProfile($user)) {
            return response()->json([
                'message' => 'Completa tu perfil antes de generar un plan de alimentación.'
            ], 422);
        }

        $plan = WeeklyPlan::create([
            'user_id'       => $user->id,
            'semana_inicio' => now()->startOfWeek()->toDateString(),
            'status'        => 'pending',
        ]);

        GenerateDietJob::dispatch($plan);

        return response()->json([
            'uuid'   => $plan->id,
            'status' => 'pending'
        ], 202);
    }

    /**
     * Retrieve the user's active plan (the most recent ready plan).
     */
    public function active(Request $request): JsonResponse
    {
        $plan = $request->user()->weeklyPlans()
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->with(['dayPlans.meals.mealItems.food'])
            ->first();

        if (!$plan) {
            return response()->json([
                'message' => 'No active plan found.'
            ], 404);
        }

        return response()->json($plan);
    }

    /**
     * Retrieve a specific weekly plan.
     */
    public function show(WeeklyPlan $weeklyPlan): JsonResponse
    {
        $this->authorize('view', $weeklyPlan);

        return response()->json(
            $weeklyPlan->load(['dayPlans.meals.mealItems.food'])
        );
    }

    /**
     * Polling endpoint — frontend checks every 2s until status = ready.
     */
    public function status(WeeklyPlan $weeklyPlan): JsonResponse
    {
        $this->authorize('view', $weeklyPlan);

        return response()->json([
            'uuid'          => $weeklyPlan->id,
            'status'        => $weeklyPlan->status,
            'error_message' => $weeklyPlan->error_message,
        ]);
    }
}
