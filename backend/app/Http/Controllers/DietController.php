<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDietJob;
use App\Models\WeeklyPlan;
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
        $plan = WeeklyPlan::create([
            'user_id'       => $request->user()->id,
            'semana_inicio' => now()->startOfWeek()->toDateString(),
            'status'        => 'pending',
        ]);

        GenerateDietJob::dispatch($plan);

        return response()->json(['plan_id' => $plan->id, 'status' => 'pending'], 202);
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
            'id'     => $weeklyPlan->id,
            'status' => $weeklyPlan->status,
        ]);
    }
}
