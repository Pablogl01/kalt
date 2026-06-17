<?php

namespace App\Jobs;

use App\Models\Food;
use App\Models\MealTemplate;
use App\Models\MealSlot;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\DietCalculator;
use App\Services\MealDistributor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateDietJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly WeeklyPlan $weeklyPlan
    ) {}

    /**
     * Execute the job — implements Phase 2.
     */
    public function handle(): void
    {
        Log::info('GenerateDietJob started for plan: ' . $this->weeklyPlan->id);

        try {
            $user = $this->weeklyPlan->user;

            // Ensure user has a complete profile
            $calculator = new DietCalculator();
            if (!$calculator->userHasCompleteProfile($user)) {
                throw new \Exception("El perfil del usuario está incompleto.");
            }

            // Get or create meal template
            $template = $user->mealTemplates()->first();
            if (!$template) {
                $template = $this->createDefaultMealTemplate($user);
            }

            $mealSlots = $template->mealSlots()->orderBy('orden')->get()->toArray();
            $numComidas = count($mealSlots);

            if ($numComidas === 0) {
                throw new \Exception("El usuario no tiene comidas configuradas.");
            }

            $startDate = $this->weeklyPlan->semana_inicio;

            DB::transaction(function () use ($user, $startDate, $calculator, $numComidas, $mealSlots) {
                // Delete existing day plans for this weekly plan to avoid duplicates if re-run
                $this->weeklyPlan->dayPlans()->delete();

                for ($i = 0; $i < 7; $i++) {
                    $date = $startDate->copy()->addDays($i);
                    $dayOfWeek = $date->dayOfWeekIso;

                    $isTraining = $this->isTrainingDay($user, $dayOfWeek);

                    // 1. Calculate macros for the day using DietCalculator
                    $dailyMacros = $calculator->calculate($user);
                    if (empty($dailyMacros)) {
                        throw new \Exception("Error al calcular macros del usuario.");
                    }

                    // Create DayPlan
                    $dayPlan = $this->weeklyPlan->dayPlans()->create([
                        'fecha'             => $date->toDateString(),
                        'calorias_objetivo' => $dailyMacros['calorias'],
                        'proteina_obj'      => $dailyMacros['proteina'],
                        'carbos_obj'        => $dailyMacros['carbos'],
                        'grasa_obj'         => $dailyMacros['grasa'],
                    ]);

                    // Create daily log for tracking
                    $user->dailyLogs()->updateOrCreate(
                        ['fecha' => $date->toDateString()],
                        ['entreno_planificado' => $isTraining]
                    );

                    // 2. Distribute macros between meals
                    $distributor = new MealDistributor();
                    $effectiveMealSlots = $mealSlots;
                    if (!$isTraining) {
                        foreach ($effectiveMealSlots as &$slot) {
                            $slot['es_pre_entreno'] = false;
                            $slot['es_post_entreno'] = false;
                        }
                    }

                    $mealsMacros = $distributor->distribute($dailyMacros, $numComidas, $effectiveMealSlots);

                    // 3. Create meals and add meal items
                    foreach ($mealSlots as $slot) {
                        $mealMacros = $mealsMacros[$slot['id']] ?? null;
                        if (!$mealMacros) {
                            continue;
                        }

                        $meal = $dayPlan->meals()->create([
                            'meal_slot_id'  => $slot['id'],
                            'nombre'        => $slot['nombre'],
                            'hora_objetivo' => $slot['hora_objetivo'],
                        ]);

                        $this->generateMealItems($meal, $mealMacros, $user);
                    }
                }
            });

            $this->weeklyPlan->update(['status' => 'ready', 'generado_en' => now()]);

            $generator = app(\App\Services\ShoppingGenerator::class);
            if (\App\Models\ShoppingList::where('weekly_plan_id', $this->weeklyPlan->id)->exists()) {
                $generator->regenerate($this->weeklyPlan);
            } else {
                $generator->generate($this->weeklyPlan);
            }

            Log::info('GenerateDietJob completed successfully for plan: ' . $this->weeklyPlan->id);

        } catch (\Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    private function isTrainingDay(User $user, int $dayOfWeek): bool
    {
        $level = $user->nivel_actividad ?? 'moderado';
        return match ($level) {
            'alto'       => in_array($dayOfWeek, [1, 2, 3, 5, 6]),
            'moderado'   => in_array($dayOfWeek, [1, 3, 5]),
            'ligero'     => in_array($dayOfWeek, [2, 4]),
            default      => false,
        };
    }

    private function createDefaultMealTemplate(User $user): MealTemplate
    {
        $objetivo = $user->objetivo ?? 'mantenimiento';
        $numComidas = match ($objetivo) {
            'definicion'    => 3,
            'volumen'       => 5,
            default         => 4,
        };

        $template = MealTemplate::create([
            'user_id'          => $user->id,
            'num_comidas'      => $numComidas,
            'es_personalizado' => false,
        ]);

        $slots = match ($numComidas) {
            3 => [
                ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
                ['orden' => 2, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00', 'es_pre_entreno' => true, 'es_post_entreno' => false],
                ['orden' => 3, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => true],
            ],
            5 => [
                ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
                ['orden' => 2, 'nombre' => 'Media mañana', 'hora_objetivo' => '11:30:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
                ['orden' => 3, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00', 'es_pre_entreno' => true, 'es_post_entreno' => false],
                ['orden' => 4, 'nombre' => 'Merienda', 'hora_objetivo' => '17:30:00', 'es_pre_entreno' => false, 'es_post_entreno' => true],
                ['orden' => 5, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
            ],
            default => [
                ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
                ['orden' => 2, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00', 'es_pre_entreno' => true, 'es_post_entreno' => false],
                ['orden' => 3, 'nombre' => 'Merienda', 'hora_objetivo' => '17:30:00', 'es_pre_entreno' => false, 'es_post_entreno' => true],
                ['orden' => 4, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00', 'es_pre_entreno' => false, 'es_post_entreno' => false],
            ],
        };

        foreach ($slots as $slot) {
            $template->mealSlots()->create($slot);
        }

        return $template;
    }

    private function generateMealItems($meal, array $targetMacros, User $user, float $tolerance = 0.10): void
    {
        // Fetch allergies or intolerances to exclude
        $restrictedFoodIds = $user->foodRestrictions
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->toArray();

        $proteins = Food::where('categoria', 'proteina')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        $carbs = Food::where('categoria', 'carbos')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        $fats = Food::where('categoria', 'grasas')
            ->whereNotIn('id', $restrictedFoodIds)
            ->get();

        // Fallbacks
        if ($proteins->isEmpty()) $proteins = Food::where('categoria', 'proteina')->get();
        if ($carbs->isEmpty()) $carbs = Food::where('categoria', 'carbos')->get();
        if ($fats->isEmpty()) $fats = Food::where('categoria', 'grasas')->get();

        if ($proteins->isEmpty() || $carbs->isEmpty() || $fats->isEmpty()) {
            throw new \Exception("Alimentos insuficientes en BD para generar el plan");
        }

        $attempts = 0;
        $maxAttempts = 50;
        $bestMix = null;
        $bestDiff = 999999;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $pFood = $proteins->random();
            $cFood = $carbs->random();
            $fFood = $fats->random();

            $gP = ($targetMacros['proteina'] / max(0.1, $pFood->proteina)) * 100;
            $gC = ($targetMacros['carbos'] / max(0.1, $cFood->carbos)) * 100;
            $gF = ($targetMacros['grasa'] / max(0.1, $fFood->grasa)) * 100;

            $gP = max(10, min(500, $gP));
            $gC = max(10, min(500, $gC));
            $gF = max(5, min(200, $gF));

            $actualProt = ($pFood->proteina * $gP / 100) + ($cFood->proteina * $gC / 100) + ($fFood->proteina * $gF / 100);
            $actualCarb = ($pFood->carbos * $gP / 100) + ($cFood->carbos * $gC / 100) + ($fFood->carbos * $gF / 100);
            $actualFat  = ($pFood->grasa * $gP / 100) + ($cFood->grasa * $gC / 100) + ($fFood->grasa * $gF / 100);

            $errProt = abs($actualProt - $targetMacros['proteina']) / max(1, $targetMacros['proteina']);
            $errCarb = abs($actualCarb - $targetMacros['carbos']) / max(1, $targetMacros['carbos']);
            $errFat  = abs($actualFat - $targetMacros['grasa']) / max(1, $targetMacros['grasa']);

            $maxError = max($errProt, $errCarb, $errFat);

            if ($maxError <= $tolerance) {
                $this->saveMealItem($meal, $pFood, $gP);
                $this->saveMealItem($meal, $cFood, $gC);
                $this->saveMealItem($meal, $fFood, $gF);
                return;
            }

            if ($maxError < $bestDiff) {
                $bestDiff = $maxError;
                $bestMix = [
                    'foods' => [$pFood, $cFood, $fFood],
                    'grams' => [$gP, $gC, $gF]
                ];
            }
        }

        // If tolerance is 10% and failed, retry with 15%
        if ($tolerance < 0.15) {
            $this->generateMealItems($meal, $targetMacros, $user, 0.15);
            return;
        }

        if ($bestMix) {
            $this->saveMealItem($meal, $bestMix['foods'][0], $bestMix['grams'][0]);
            $this->saveMealItem($meal, $bestMix['foods'][1], $bestMix['grams'][1]);
            $this->saveMealItem($meal, $bestMix['foods'][2], $bestMix['grams'][2]);
            return;
        }

        throw new \Exception("No se pudo cuadrar los macros de la comida.");
    }

    private function saveMealItem($meal, $food, float $grams): void
    {
        $meal->mealItems()->create([
            'food_id'         => $food->id,
            'cantidad_gramos' => round($grams, 1),
            'calorias'        => round($food->calorias * $grams / 100, 1),
            'proteina'        => round($food->proteina * $grams / 100, 1),
            'carbos'          => round($food->carbos * $grams / 100, 1),
            'grasa'           => round($food->grasa * $grams / 100, 1),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->weeklyPlan->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage()
        ]);
        Log::error('GenerateDietJob failed: ' . $exception->getMessage());
    }
}
