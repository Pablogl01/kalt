<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMealLogRequest;
use App\Models\DailyLog;
use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = DailyLog::where('user_id', $request->user()->id)
            ->with(['mealLogs.mealLogItems.food', 'mealLogs.meal'])
            ->orderByDesc('fecha')
            ->paginate(30);

        return response()->json($logs);
    }

    public function store(StoreMealLogRequest $request): JsonResponse
    {
        // Business logic — implemented in Phase 3
        return response()->json([], 201);
    }

    public function show(MealLog $mealLog): JsonResponse
    {
        $this->authorize('view', $mealLog);

        return response()->json(
            $mealLog->load(['mealLogItems.food'])
        );
    }

    public function update(StoreMealLogRequest $request, MealLog $mealLog): JsonResponse
    {
        $this->authorize('update', $mealLog);

        // Business logic — implemented in Phase 3
        return response()->json($mealLog->fresh());
    }

    public function destroy(MealLog $mealLog): JsonResponse
    {
        $this->authorize('delete', $mealLog);

        $mealLog->delete();

        return response()->json(null, 204);
    }
}
