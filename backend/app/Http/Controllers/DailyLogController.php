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

        $haEntrenado = $validated['ha_entrenado'];
        $horaGimnasio = $validated['hora_gimnasio'] ?? $dailyLog->hora_gimnasio;

        $recalMotivo = null;
        $recalMsg = null;

        if ($dailyLog->entreno_planificado && !$haEntrenado) {
            $dailyLog->update([
                'ha_entrenado' => false,
                'hora_gimnasio' => null,
            ]);

            $dayPlan = $dailyLog->user->weeklyPlans()
                ->where('status', 'ready')
                ->where('semana_inicio', '<=', $dailyLog->fecha->toDateString())
                ->orderBy('semana_inicio', 'desc')
                ->first()?->dayPlans()->whereDate('fecha', $dailyLog->fecha->toDateString())->first();

            $oldCal = $dayPlan ? $dayPlan->calorias_objetivo : 2000;

            $recalculator = new \App\Services\DayRecalculator();
            $recalculator->recalculateNoTraining($dailyLog);

            $dayPlan?->refresh();
            $newCal = $dayPlan ? $dayPlan->calorias_objetivo : 2000;
            $reducedCal = round($oldCal - $newCal);

            $remainingCount = $dailyLog->mealLogs()->where('realizada', false)->where('es_extra', false)->count();
            $recalMsg = $remainingCount > 0
                ? "Como no has entrenado hoy, hemos reducido {$reducedCal} kcal de tus comidas restantes."
                : "No quedan comidas para ajustar hoy.";
            $recalMotivo = 'no_ha_entrenado';
        } elseif (!$dailyLog->entreno_planificado && $haEntrenado) {
            if (empty($horaGimnasio)) {
                return response()->json([
                    'message' => 'La hora de gimnasio es obligatoria para registrar un entrenamiento hoy.',
                    'errors' => [
                        'hora_gimnasio' => ['La hora de gimnasio es obligatoria.']
                    ]
                ], 422);
            }

            $dailyLog->update([
                'ha_entrenado' => true,
                'hora_gimnasio' => $horaGimnasio,
            ]);

            $recalculator = new \App\Services\DayRecalculator();
            $recalculator->recalculateExtraTraining($dailyLog);

            $remainingCount = $dailyLog->mealLogs()->where('realizada', false)->where('es_extra', false)->count();
            $recalMsg = $remainingCount > 0
                ? "Hemos añadido 400 kcal a tus comidas de hoy por el entreno no planificado."
                : "No quedan comidas para ajustar hoy.";
            $recalMotivo = 'entreno_no_planificado';
        } else {
            $dailyLog->update([
                'ha_entrenado' => $haEntrenado,
                'hora_gimnasio' => $horaGimnasio,
                'recalculo_motivo' => $haEntrenado ? 'entreno_realizado' : 'entreno_no_realizado',
            ]);
        }

        $response = $this->formatLogResponse($dailyLog);
        $data = $response->getData(true);
        if ($recalMotivo) {
            $data['recalculo_motivo'] = $recalMotivo;
            $data['mensaje_recalculo'] = $recalMsg;
        }

        return response()->json($data);
    }

    public function undoRecalc(DailyLog $dailyLog): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        $recalculator = new \App\Services\DayRecalculator();

        if (!$recalculator->restoreLastSnapshot($dailyLog)) {
            return response()->json([
                'message' => 'No hay ningún recálculo reciente que deshacer.',
            ], 422);
        }

        return $this->formatLogResponse($dailyLog->fresh());
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

        // Persistent "ajustado" mark: while a recalculation is active, annotate
        // each affected meal with the kcal delta vs. its pre-recalc total.
        $originals = $log->recalculo_snapshot['meal_originals'] ?? [];
        if ($log->recalculo_motivo && !empty($originals) && !empty($data['meal_logs'])) {
            foreach ($data['meal_logs'] as &$ml) {
                $mealId = $ml['meal_id'] ?? null;
                if ($mealId && isset($originals[$mealId]) && !empty($ml['meal']['meal_items'])) {
                    $currentCal = array_sum(array_column($ml['meal']['meal_items'], 'calorias'));
                    $delta = round($currentCal - $originals[$mealId]);
                    if (abs($delta) >= 1) {
                        $ml['ajuste_kcal'] = $delta;
                    }
                }
            }
            unset($ml);
        }

        return response()->json($data);
    }

    private function isTrainingDay($user, int $dayOfWeek): bool
    {
        $level = $user->nivel_actividad ?? 'moderado';
        return match ($level) {
            'alto'     => in_array($dayOfWeek, [1, 2, 3, 5, 6]),
            'moderado' => in_array($dayOfWeek, [1, 3, 5]),
            'ligero'   => in_array($dayOfWeek, [2, 4]),
            default    => false,
        };
    }
}
