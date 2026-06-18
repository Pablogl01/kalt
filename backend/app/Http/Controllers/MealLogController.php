<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMealLogRequest;
use App\Models\DailyLog;
use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function complete(MealLog $mealLog): JsonResponse
    {
        $this->authorize('update', $mealLog);

        $mealLog->update([
            'realizada' => true,
            'saltada'   => false,
            'hora_real' => now()->format('H:i:s'),
        ]);

        if ($mealLog->meal && $mealLog->mealLogItems()->count() === 0) {
            foreach ($mealLog->meal->mealItems as $item) {
                $mealLog->mealLogItems()->create([
                    'food_id'         => $item->food_id,
                    'cantidad_gramos' => $item->cantidad_gramos,
                    'calorias'        => $item->calorias,
                    'proteina'        => $item->proteina,
                    'carbos'          => $item->carbos,
                    'grasa'           => $item->grasa,
                ]);
            }
        }

        return response()->json($mealLog->load(['mealLogItems.food', 'meal.mealSlot', 'meal.mealItems.food']));
    }

    public function skip(MealLog $mealLog): JsonResponse
    {
        $this->authorize('update', $mealLog);

        $mealLog->update([
            'realizada' => false,
            'saltada'   => true,
            'hora_real' => null,
        ]);

        $recalculator = new \App\Services\DayRecalculator();
        $recalculator->recalculateSkippedMeal($mealLog->dailyLog, $mealLog);

        $skippedCal = 0;
        if ($mealLog->meal) {
            $skippedCal = round($mealLog->meal->mealItems->sum('calorias'));
        }
        $time = $mealLog->meal ? substr($mealLog->meal->hora_objetivo, 0, 5) : '';

        $remainingCount = $mealLog->dailyLog->mealLogs()->where('realizada', false)->where('saltada', false)->where('es_extra', false)->count();
        $msg = $remainingCount > 0 
            ? "Tu comida de las {$time} fue saltada. Hemos redistribuido {$skippedCal} kcal entre tus comidas restantes."
            : "No quedan comidas para ajustar hoy.";

        $data = $mealLog->fresh()->load(['mealLogItems.food', 'meal.mealSlot', 'meal.mealItems.food'])->toArray();
        $data['recalculo_motivo'] = 'comida_saltada';
        $data['mensaje_recalculo'] = $msg;

        return response()->json($data);
    }

    /**
     * Revert a meal back to pending. For a completed meal it removes the
     * auto-created items; for a skipped meal it also undoes the macro
     * redistribution that the skip triggered. Returns the refreshed DailyLog.
     */
    public function reset(MealLog $mealLog): JsonResponse
    {
        $this->authorize('update', $mealLog);

        $wasSkipped = $mealLog->saltada;

        if ($mealLog->realizada && !$mealLog->es_extra) {
            $mealLog->mealLogItems()->delete();
        }

        $mealLog->update([
            'realizada' => false,
            'saltada'   => false,
            'hora_real' => null,
        ]);

        if ($wasSkipped && $mealLog->dailyLog) {
            $recalculator = new \App\Services\DayRecalculator();
            $recalculator->restoreLastSnapshot($mealLog->dailyLog);
            $mealLog->dailyLog->update(['recalculo_motivo' => null]);
        }

        $dailyLog = $mealLog->dailyLog->fresh()->load([
            'mealLogs.mealLogItems.food',
            'mealLogs.meal.mealSlot',
            'mealLogs.meal.mealItems.food',
        ]);

        return response()->json($dailyLog);
    }

    public function extra(DailyLog $dailyLog, Request $request): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $validated = $request->validate([
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.food_id'         => ['required', 'uuid', 'exists:foods,id'],
            'items.*.cantidad_gramos' => ['required', 'numeric', 'min:1'],
        ]);

        $mealLog = DB::transaction(function () use ($dailyLog, $validated) {
            $mealLog = MealLog::create([
                'daily_log_id' => $dailyLog->id,
                'meal_id'      => null,
                'es_extra'     => true,
                'realizada'    => true,
                'hora_real'    => now()->format('H:i:s'),
            ]);

            foreach ($validated['items'] as $item) {
                $food = \App\Models\Food::find($item['food_id']);
                $grams = $item['cantidad_gramos'];

                $mealLog->mealLogItems()->create([
                    'food_id'         => $food->id,
                    'cantidad_gramos' => $grams,
                    'calorias'        => round(($food->calorias * $grams) / 100, 2),
                    'proteina'        => round(($food->proteina * $grams) / 100, 2),
                    'carbos'          => round(($food->carbos * $grams) / 100, 2),
                    'grasa'           => round(($food->grasa * $grams) / 100, 2),
                ]);
            }

            return $mealLog;
        });

        $recalculator = new \App\Services\DayRecalculator();
        $recalculator->recalculateExtraMeal($dailyLog, $mealLog);

        $extraCal = round($mealLog->mealLogItems->sum('calorias'));
        $time = substr($mealLog->hora_real, 0, 5);

        $remainingCount = $dailyLog->mealLogs()->where('realizada', false)->where('es_extra', false)->count();
        $msg = $remainingCount > 0
            ? "Hemos ajustado tus comidas restantes porque añadiste {$extraCal} kcal extra a las {$time}."
            : "No quedan comidas para ajustar hoy.";

        $data = $mealLog->fresh()->load('mealLogItems.food')->toArray();
        $data['recalculo_motivo'] = 'comida_extra';
        $data['mensaje_recalculo'] = $msg;

        return response()->json($data, 201);
    }
}
