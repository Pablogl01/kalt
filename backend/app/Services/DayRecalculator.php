<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\MealItem;
use App\Models\Meal;
use App\Models\DayPlan;
use Carbon\Carbon;

/**
 * Handles dynamic recalculation of daily macros based on real-time events.
 * Cases: A (skipped meal), B (missed workout), C (unplanned workout), D (extra meal).
 * Unit tests: tests/Unit/Services/DayRecalculatorTest.php (Phase 4)
 */
class DayRecalculator
{
    public function recalculateSkippedMeal(DailyLog $log, MealLog $skippedMeal): void
    {
        $skippedCal = 0;
        $skippedProt = 0;
        $skippedCarb = 0;
        $skippedFat = 0;

        if ($skippedMeal->meal) {
            foreach ($skippedMeal->meal->mealItems as $item) {
                $skippedCal += $item->calorias;
                $skippedProt += $item->proteina;
                $skippedCarb += $item->carbos;
                $skippedFat += $item->grasa;
            }
        }

        $remainingLogs = $log->mealLogs()
            ->where('realizada', false)
            ->where('saltada', false)
            ->where('es_extra', false)
            ->where('id', '!=', $skippedMeal->id)
            ->with(['meal.mealItems.food'])
            ->get();

        if ($remainingLogs->isEmpty()) {
            $log->update([
                'recalculo_motivo' => 'comida_saltada',
            ]);
            return;
        }

        // Snapshot the meals we are about to rescale so the skip can be undone.
        $this->captureSnapshot($log, $remainingLogs);

        $totalOriginalCal = 0;
        foreach ($remainingLogs as $rLog) {
            if ($rLog->meal) {
                $totalOriginalCal += $rLog->meal->mealItems->sum('calorias');
            }
        }

        foreach ($remainingLogs as $rLog) {
            if (!$rLog->meal) continue;

            $meal = $rLog->meal;
            $mealOriginalCal = $meal->mealItems->sum('calorias');

            if ($totalOriginalCal > 0) {
                $proportion = $mealOriginalCal / $totalOriginalCal;
            } else {
                $proportion = 1 / $remainingLogs->count();
            }

            $extraCal = $skippedCal * $proportion;
            $extraProt = $skippedProt * $proportion;
            $extraCarb = $skippedCarb * $proportion;
            $extraFat = $skippedFat * $proportion;

            $newCal = $mealOriginalCal + $extraCal;
            $newProt = $meal->mealItems->sum('proteina') + $extraProt;
            $newCarb = $meal->mealItems->sum('carbos') + $extraCarb;
            $newFat = $meal->mealItems->sum('grasa') + $extraFat;

            $this->scaleMealItems($meal, $newCal, $newProt, $newCarb, $newFat);
        }

        $log->update([
            'recalculo_motivo' => 'comida_saltada',
        ]);
    }

    public function recalculateNoTraining(DailyLog $log): void
    {
        $dayPlan = $this->getDayPlan($log);
        if (!$dayPlan) {
            $log->update(['recalculo_motivo' => 'no_ha_entrenado']);
            return;
        }

        $targetCal = $dayPlan->calorias_objetivo;
        $reductionKcal = min(400, max(200, $targetCal * 0.10));

        $remainingLogs = $log->mealLogs()
            ->where('realizada', false)
            ->where('saltada', false)
            ->where('es_extra', false)
            ->with(['meal.mealItems'])
            ->get();

        if ($remainingLogs->isEmpty()) {
            $log->update(['recalculo_motivo' => 'no_ha_entrenado']);
            return;
        }

        // Snapshot meals and day plan before reducing, so the change can be undone.
        $this->captureSnapshot($log, $remainingLogs, $dayPlan);

        $totalCarbs = 0;
        $totalFat = 0;
        $totalProtein = 0;
        foreach ($remainingLogs as $rLog) {
            if ($rLog->meal) {
                $totalCarbs += $rLog->meal->mealItems->sum('carbos');
                $totalFat += $rLog->meal->mealItems->sum('grasa');
                $totalProtein += $rLog->meal->mealItems->sum('proteina');
            }
        }

        $carbKcal = $totalCarbs * 4;
        $newTotalCarbs = $totalCarbs;
        $newTotalFat = $totalFat;
        $newTotalProtein = $totalProtein;

        if ($carbKcal >= $reductionKcal) {
            $newTotalCarbs -= ($reductionKcal / 4);
        } else {
            $newTotalCarbs = 0;
            $remainingReduction = $reductionKcal - $carbKcal;
            
            $fatKcal = $totalFat * 9;
            if ($fatKcal >= $remainingReduction) {
                $newTotalFat -= ($remainingReduction / 9);
            } else {
                $newTotalFat = 0;
                $remainingReduction -= $fatKcal;
                
                $newTotalProtein = max(0, $totalProtein - ($remainingReduction / 4));
            }
        }

        foreach ($remainingLogs as $rLog) {
            if (!$rLog->meal) continue;

            $meal = $rLog->meal;
            $oldMealCarbs = $meal->mealItems->sum('carbos');
            $oldMealFat = $meal->mealItems->sum('grasa');
            $oldMealProt = $meal->mealItems->sum('proteina');

            $mealCarbFactor = $totalCarbs > 0 ? $oldMealCarbs / $totalCarbs : 0;
            $mealFatFactor = $totalFat > 0 ? $oldMealFat / $totalFat : 0;
            $mealProtFactor = $totalProtein > 0 ? $oldMealProt / $totalProtein : 0;

            $newMealCarbs = $newTotalCarbs * $mealCarbFactor;
            $newMealFat = $newTotalFat * $mealFatFactor;
            $newMealProt = $newTotalProtein * $mealProtFactor;
            $newMealCal = ($newMealCarbs * 4) + ($newMealProt * 4) + ($newMealFat * 9);

            $this->scaleMealItems($meal, $newMealCal, $newMealProt, $newMealCarbs, $newMealFat);
        }

        $dayPlan->update([
            'calorias_objetivo' => round($dayPlan->calorias_objetivo - $reductionKcal, 1),
            'carbos_obj' => round(max(0, $dayPlan->carbos_obj - ($totalCarbs - $newTotalCarbs)), 1),
            'grasa_obj' => round(max(0, $dayPlan->grasa_obj - ($totalFat - $newTotalFat)), 1),
            'proteina_obj' => round(max(0, $dayPlan->proteina_obj - ($totalProtein - $newTotalProtein)), 1),
        ]);

        $log->update(['recalculo_motivo' => 'no_ha_entrenado']);
    }

    public function recalculateExtraTraining(DailyLog $log): void
    {
        if (empty($log->hora_gimnasio)) {
            throw new \InvalidArgumentException("La hora de gimnasio es obligatoria para el recálculo por entreno.");
        }

        $dayPlan = $this->getDayPlan($log);
        $increaseKcal = 400;

        $remainingLogs = $log->mealLogs()
            ->where('realizada', false)
            ->where('saltada', false)
            ->where('es_extra', false)
            ->with(['meal.mealSlot'])
            ->get();

        if ($remainingLogs->isEmpty()) {
            $log->update([
                'nota_exceso' => "No quedan comidas para ajustar hoy. Exceso de {$increaseKcal} kcal por entrenamiento extra no planificado.",
                'recalculo_motivo' => 'entreno_no_planificado',
            ]);
            return;
        }

        // Snapshot meals and day plan before adding macros, so the change can be undone.
        $this->captureSnapshot($log, $remainingLogs, $dayPlan);

        $gymTime = Carbon::parse($log->hora_gimnasio);

        $preMealLog = null;
        $postMealLog = null;
        $minPreDiff = null;
        $minPostDiff = null;

        foreach ($remainingLogs as $rLog) {
            if (!$rLog->meal || !$rLog->meal->hora_objetivo) continue;

            $mealTime = Carbon::parse($rLog->meal->hora_objetivo);
            $diffSeconds = $gymTime->diffInSeconds($mealTime, false);

            if ($diffSeconds <= 0) {
                $absDiff = abs($diffSeconds);
                if (is_null($minPreDiff) || $absDiff < $minPreDiff) {
                    $minPreDiff = $absDiff;
                    $preMealLog = $rLog;
                }
            } else {
                if (is_null($minPostDiff) || $diffSeconds < $minPostDiff) {
                    $minPostDiff = $diffSeconds;
                    $postMealLog = $rLog;
                }
            }
        }

        if ($preMealLog && $postMealLog) {
            $this->addMacrosToMeal($preMealLog->meal, 200, 0, 50, 0);
            $this->addMacrosToMeal($postMealLog->meal, 200, 50, 0, 0);
        } elseif ($preMealLog) {
            $this->addMacrosToMeal($preMealLog->meal, 400, 50, 50, 0);
        } elseif ($postMealLog) {
            $this->addMacrosToMeal($postMealLog->meal, 400, 50, 50, 0);
        }

        if ($dayPlan) {
            $dayPlan->update([
                'calorias_objetivo' => round($dayPlan->calorias_objetivo + $increaseKcal, 1),
                'carbos_obj' => round($dayPlan->carbos_obj + 50, 1),
                'proteina_obj' => round($dayPlan->proteina_obj + 50, 1),
            ]);
        }

        $log->update(['recalculo_motivo' => 'entreno_no_planificado']);
    }

    public function recalculateExtraMeal(DailyLog $log, MealLog $extraMeal): void
    {
        $extraCal = 0;
        $extraProt = 0;
        $extraCarb = 0;
        $extraFat = 0;

        foreach ($extraMeal->mealLogItems as $item) {
            $extraCal += $item->calorias;
            $extraProt += $item->proteina;
            $extraCarb += $item->carbos;
            $extraFat += $item->grasa;
        }

        $dayPlan = $this->getDayPlan($log);
        if (!$dayPlan) {
            $log->update(['recalculo_motivo' => 'comida_extra']);
            return;
        }

        $completedLogs = $log->mealLogs()
            ->where('realizada', true)
            ->with('mealLogItems')
            ->get();

        $totalConsumedCal = 0;
        foreach ($completedLogs as $cLog) {
            $totalConsumedCal += $cLog->mealLogItems->sum('calorias');
        }

        if ($totalConsumedCal >= $dayPlan->calorias_objetivo) {
            $log->update(['recalculo_motivo' => 'comida_extra']);
            return;
        }

        $remainingLogs = $log->mealLogs()
            ->where('realizada', false)
            ->where('saltada', false)
            ->where('es_extra', false)
            ->with(['meal.mealItems'])
            ->get();

        if ($remainingLogs->isEmpty()) {
            $log->update(['recalculo_motivo' => 'comida_extra']);
            return;
        }

        // Snapshot remaining meals before reducing, so the change can be undone.
        $this->captureSnapshot($log, $remainingLogs);

        $totalOriginalRemainingCal = 0;
        foreach ($remainingLogs as $rLog) {
            if ($rLog->meal) {
                $totalOriginalRemainingCal += $rLog->meal->mealItems->sum('calorias');
            }
        }

        foreach ($remainingLogs as $rLog) {
            if (!$rLog->meal) continue;

            $meal = $rLog->meal;
            $mealOriginalCal = $meal->mealItems->sum('calorias');

            if ($totalOriginalRemainingCal > 0) {
                $proportion = $mealOriginalCal / $totalOriginalRemainingCal;
            } else {
                $proportion = 1 / $remainingLogs->count();
            }

            $reducedCal = max(0, $mealOriginalCal - ($extraCal * $proportion));
            $reducedProt = max(0, $meal->mealItems->sum('proteina') - ($extraProt * $proportion));
            $reducedCarb = max(0, $meal->mealItems->sum('carbos') - ($extraCarb * $proportion));
            $reducedFat = max(0, $meal->mealItems->sum('grasa') - ($extraFat * $proportion));

            $this->scaleMealItems($meal, $reducedCal, $reducedProt, $reducedCarb, $reducedFat);
        }

        $log->update(['recalculo_motivo' => 'comida_extra']);
    }

    /**
     * Store the previous quantities/macros of the meal items about to be
     * rescaled, so the recalculation can be reverted. Only the latest
     * recalculation is kept (single-step undo).
     */
    private function captureSnapshot(DailyLog $log, $affectedLogs, ?DayPlan $dayPlan = null): void
    {
        $items = [];
        $mealOriginals = [];

        foreach ($affectedLogs as $rLog) {
            if (!$rLog->meal) {
                continue;
            }
            // Per-meal original total, used for the persistent "ajustado" mark.
            $mealOriginals[$rLog->meal->id] = round($rLog->meal->mealItems->sum('calorias'), 2);

            foreach ($rLog->meal->mealItems as $item) {
                $items[] = [
                    'id'              => $item->id,
                    'cantidad_gramos' => $item->cantidad_gramos,
                    'calorias'        => $item->calorias,
                    'proteina'        => $item->proteina,
                    'carbos'          => $item->carbos,
                    'grasa'           => $item->grasa,
                ];
            }
        }

        $snapshot = [
            'meal_items'     => $items,
            'meal_originals' => $mealOriginals,
        ];

        if ($dayPlan) {
            $snapshot['day_plan'] = [
                'id'                => $dayPlan->id,
                'calorias_objetivo' => $dayPlan->calorias_objetivo,
                'proteina_obj'      => $dayPlan->proteina_obj,
                'carbos_obj'        => $dayPlan->carbos_obj,
                'grasa_obj'         => $dayPlan->grasa_obj,
            ];
        }

        $log->update(['recalculo_snapshot' => $snapshot]);
    }

    /**
     * Revert the last recalculation by restoring the snapshot quantities and
     * clearing the recalculation state. Returns false if there is no snapshot.
     */
    public function restoreLastSnapshot(DailyLog $log): bool
    {
        $snapshot = $log->recalculo_snapshot;

        if (empty($snapshot) || empty($snapshot['meal_items'])) {
            return false;
        }

        foreach ($snapshot['meal_items'] as $row) {
            $item = MealItem::find($row['id']);
            if (!$item) {
                continue;
            }
            $item->update([
                'cantidad_gramos' => $row['cantidad_gramos'],
                'calorias'        => $row['calorias'],
                'proteina'        => $row['proteina'],
                'carbos'          => $row['carbos'],
                'grasa'           => $row['grasa'],
            ]);
        }

        if (!empty($snapshot['day_plan'])) {
            $dayPlan = DayPlan::find($snapshot['day_plan']['id']);
            if ($dayPlan) {
                $dayPlan->update([
                    'calorias_objetivo' => $snapshot['day_plan']['calorias_objetivo'],
                    'proteina_obj'      => $snapshot['day_plan']['proteina_obj'],
                    'carbos_obj'        => $snapshot['day_plan']['carbos_obj'],
                    'grasa_obj'         => $snapshot['day_plan']['grasa_obj'],
                ]);
            }
        }

        $log->update([
            'recalculo_motivo'   => null,
            'nota_exceso'        => null,
            'recalculo_snapshot' => null,
        ]);

        return true;
    }

    private function getDayPlan(DailyLog $log)
    {
        $dateStr = \Carbon\Carbon::parse($log->fecha)->toDateString();
        $weeklyPlan = \App\Models\WeeklyPlan::where('user_id', $log->user_id)
            ->where('status', 'ready')
            ->whereDate('semana_inicio', '<=', $dateStr)
            ->orderBy('semana_inicio', 'desc')
            ->first();

        return $weeklyPlan ? $weeklyPlan->dayPlans()->whereDate('fecha', $dateStr)->first() : null;
    }

    private function addMacrosToMeal(Meal $meal, float $cal, float $prot, float $carb, float $fat): void
    {
        $currentCal = $meal->mealItems->sum('calorias');
        $currentProt = $meal->mealItems->sum('proteina');
        $currentCarb = $meal->mealItems->sum('carbos');
        $currentFat = $meal->mealItems->sum('grasa');

        $this->scaleMealItems($meal, $currentCal + $cal, $currentProt + $prot, $currentCarb + $carb, $currentFat + $fat);
    }

    private function scaleMealItems(Meal $meal, float $calTarget, float $protTarget, float $carbTarget, float $fatTarget): void
    {
        $items = $meal->mealItems;
        if ($items->isEmpty()) {
            return;
        }

        $currentCal = $items->sum('calorias');

        if ($currentCal > 0) {
            $factor = $calTarget / $currentCal;
            foreach ($items as $item) {
                $food = $item->food;
                if (!$food) continue;
                $newGrams = $item->cantidad_gramos * $factor;
                $item->update([
                    'cantidad_gramos' => round($newGrams, 2),
                    'calorias' => round(($food->calorias * $newGrams) / 100, 2),
                    'proteina' => round(($food->proteina * $newGrams) / 100, 2),
                    'carbos' => round(($food->carbos * $newGrams) / 100, 2),
                    'grasa' => round(($food->grasa * $newGrams) / 100, 2),
                ]);
            }
        } else {
            $count = $items->count();
            foreach ($items as $item) {
                $food = $item->food;
                if (!$food) continue;
                $itemCal = $calTarget / $count;
                $newGrams = $food->calorias > 0 ? ($itemCal / $food->calorias) * 100 : 0;
                $item->update([
                    'cantidad_gramos' => round($newGrams, 2),
                    'calorias' => round($itemCal, 2),
                    'proteina' => round($protTarget / $count, 2),
                    'carbos' => round($carbTarget / $count, 2),
                    'grasa' => round($fatTarget / $count, 2),
                ]);
            }
        }
    }
}
