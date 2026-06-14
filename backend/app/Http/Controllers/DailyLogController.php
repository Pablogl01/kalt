<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DailyLogController extends Controller
{
    public function show(string $fecha, Request $request): JsonResponse
    {
        $user = $request->user();

        // If it's a UUID, look up by ID and authorize
        if (Str::isUuid($fecha)) {
            $log = DailyLog::find($fecha);
            if (!$log) {
                return response()->json(['message' => 'Not found.'], 404);
            }
            $this->authorize('view', $log);
            return $this->formatLogResponse($log);
        }

        // Find by date
        $log = DailyLog::where('user_id', $user->id)
            ->whereDate('fecha', $fecha)
            ->first();

        if (!$log) {
            $dayOfWeek = \Carbon\Carbon::parse($fecha)->dayOfWeekIso;
            $isTraining = $this->isTrainingDay($user, $dayOfWeek);

            $log = DailyLog::create([
                'user_id'             => $user->id,
                'fecha'               => $fecha,
                'entreno_planificado' => $isTraining,
                'ha_entrenado'        => $isTraining,
            ]);

            $template = $user->mealTemplates()->first();
            if ($template) {
                $weeklyPlan = $user->weeklyPlans()
                    ->where('status', 'ready')
                    ->where('semana_inicio', '<=', $fecha)
                    ->orderBy('semana_inicio', 'desc')
                    ->first();

                $dayPlan = null;
                if ($weeklyPlan) {
                    $dayPlan = $weeklyPlan->dayPlans()->whereDate('fecha', $fecha)->first();
                }

                $slots = $template->mealSlots()->orderBy('orden')->get();
                foreach ($slots as $slot) {
                    $meal = null;
                    if ($dayPlan) {
                        $meal = $dayPlan->meals()->where('meal_slot_id', $slot->id)->first();
                    }

                    MealLog::create([
                        'daily_log_id' => $log->id,
                        'meal_id'      => $meal?->id,
                        'es_extra'     => false,
                        'realizada'    => false,
                    ]);
                }
            }
        }

        return $this->formatLogResponse($log);
    }

    public function updateTraining(DailyLog $dailyLog, Request $request): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $validated = $request->validate([
            'ha_entrenado'  => ['required', 'boolean'],
            'hora_gimnasio' => ['nullable', 'string'],
        ]);

        $dailyLog->update([
            'ha_entrenado'     => $validated['ha_entrenado'],
            'hora_gimnasio'    => $validated['hora_gimnasio'] ?? null,
            'recalculo_motivo' => $validated['ha_entrenado'] ? 'entreno_realizado' : 'entreno_no_realizado',
        ]);

        return $this->formatLogResponse($dailyLog);
    }

    private function formatLogResponse(DailyLog $log): JsonResponse
    {
        $log->load([
            'mealLogs.mealLogItems.food', 
            'mealLogs.meal.mealSlot', 
            'mealLogs.meal.mealItems.food'
        ]);

        $weeklyPlan = $log->user->weeklyPlans()
            ->where('status', 'ready')
            ->where('semana_inicio', '<=', $log->fecha->toDateString())
            ->orderBy('semana_inicio', 'desc')
            ->first();

        $dayPlan = null;
        if ($weeklyPlan) {
            $dayPlan = $weeklyPlan->dayPlans()->whereDate('fecha', $log->fecha->toDateString())->first();
        }

        $data = $log->toArray();
        $data['day_plan'] = $dayPlan ? $dayPlan->toArray() : null;

        return response()->json($data);
    }

    private function isTrainingDay($user, int $dayOfWeek): bool
    {
        $level = $user->nivel_actividad ?? 'sedentario';
        return match ($level) {
            'alto'     => in_array($dayOfWeek, [1, 2, 3, 5, 6]),
            'moderado' => in_array($dayOfWeek, [1, 3, 5]),
            'ligero'   => in_array($dayOfWeek, [2, 4]),
            default    => false,
        };
    }
}
