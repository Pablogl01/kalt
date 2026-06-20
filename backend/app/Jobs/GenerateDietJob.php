<?php

namespace App\Jobs;

use App\Models\MealTemplate;
use App\Models\MealSlot;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\DietCalculator;
use App\Services\MealDistributor;
use App\Services\MealPlateGenerator;
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

            // Supplements that count towards macros (e.g. whey) are injected as
            // fixed meal items; their macros are subtracted from that meal's target.
            $macroSupplements = $user->supplements()
                ->where('afecta_macros', true)
                ->with('food')
                ->get();
            // Supplement foods are added only via injection, never picked at random.
            $supplementFoodIds = $macroSupplements->pluck('food_id')->all();

            DB::transaction(function () use ($user, $startDate, $calculator, $numComidas, $mealSlots, $macroSupplements, $supplementFoodIds) {
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

                    // Map each macro supplement to the slot where it should be injected.
                    $injections = $this->resolveSupplementInjections($effectiveMealSlots, $macroSupplements);

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

                        app(MealPlateGenerator::class)->build($meal, $mealMacros, $user, $injections[$slot['id']] ?? [], $supplementFoodIds);
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

    /**
     * Build a map of slotId => [['food'=>Food,'grams'=>float], ...] for the day's slots.
     *
     * @param array $slots  effective meal slots for the day (pre/post flags already adjusted)
     */
    private function resolveSupplementInjections(array $slots, $supplements): array
    {
        $map = [];
        foreach ($supplements as $supp) {
            if (!$supp->food) {
                continue;
            }
            $slotId = $this->pickSlotForMomento($slots, $supp->momento);
            if (!$slotId) {
                continue;
            }
            $map[$slotId][] = ['food' => $supp->food, 'grams' => (float) $supp->dosis_gramos];
        }
        return $map;
    }

    /**
     * Pick which slot a supplement belongs to, by its "momento". Falls back to
     * the first slot when the preferred one isn't present that day (e.g. no
     * post-workout meal on a rest day).
     */
    private function pickSlotForMomento(array $slots, string $momento): ?string
    {
        if ($momento === 'post_entreno' || $momento === 'pre_entreno') {
            $flag = $momento === 'post_entreno' ? 'es_post_entreno' : 'es_pre_entreno';
            foreach ($slots as $s) {
                if (!empty($s[$flag])) {
                    return $s['id'];
                }
            }
        }

        if ($momento === 'desayuno' || $momento === 'cena') {
            foreach ($slots as $s) {
                if (stripos($s['nombre'] ?? '', $momento) !== false) {
                    return $s['id'];
                }
            }
        }

        return $slots[0]['id'] ?? null;
    }

    private function isTrainingDay(User $user, int $dayOfWeek): bool
    {
        // Prefer the user's explicit training days; fall back to the activity preset.
        if (is_array($user->dias_entreno)) {
            return in_array($dayOfWeek, $user->dias_entreno);
        }

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

    public function failed(\Throwable $exception): void
    {
        $this->weeklyPlan->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage()
        ]);
        Log::error('GenerateDietJob failed: ' . $exception->getMessage());
    }
}
