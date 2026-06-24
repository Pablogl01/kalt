<?php

namespace App\Http\Controllers;

use App\Http\Requests\Step1Request;
use App\Http\Requests\Step2Request;
use App\Http\Requests\Step3Request;
use App\Jobs\GenerateDietJob;
use App\Models\MealTemplate;
use App\Models\WeeklyPlan;
use App\Services\DietCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    protected DietCalculator $calculator;

    public function __construct(DietCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function step1(Step1Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());
        $user->refresh();

        // Calculate macros in real-time
        $activityLevel = $user->nivel_actividad ?? 'moderado';
        $bmr = $this->calculator->calculateBMR($user);
        $tdee = $this->calculator->calculateTDEE($bmr, $activityLevel);
        $macros = $this->calculator->calculateMacros($tdee, $user->objetivo);

        return response()->json([
            'user' => $user,
            'macros' => $macros,
        ]);
    }

    public function step2(Step2Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'nivel_actividad' => $validated['nivel_actividad'],
                'dias_entreno'    => $validated['dias_entreno'],
            ]);

            // Create or update meal template
            $template = $user->mealTemplates()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'num_comidas' => $validated['num_comidas'],
                    'es_personalizado' => false,
                ]
            );

            // Recreate MealSlots
            $template->mealSlots()->delete();

            $numComidas = $validated['num_comidas'];
            $slots = [];

            if ($numComidas === 3) {
                $slots = [
                    ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00'],
                    ['orden' => 2, 'nombre' => 'Comida', 'hora_objetivo' => '14:00:00'],
                    ['orden' => 3, 'nombre' => 'Cena', 'hora_objetivo' => '20:00:00'],
                ];
            } elseif ($numComidas === 4) {
                $slots = [
                    ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00'],
                    ['orden' => 2, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00'],
                    ['orden' => 3, 'nombre' => 'Merienda', 'hora_objetivo' => '17:30:00'],
                    ['orden' => 4, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00'],
                ];
            } elseif ($numComidas === 5) {
                $slots = [
                    ['orden' => 1, 'nombre' => 'Desayuno', 'hora_objetivo' => '08:00:00'],
                    ['orden' => 2, 'nombre' => 'Media mañana', 'hora_objetivo' => '11:30:00'],
                    ['orden' => 3, 'nombre' => 'Almuerzo', 'hora_objetivo' => '14:00:00'],
                    ['orden' => 4, 'nombre' => 'Merienda', 'hora_objetivo' => '17:30:00'],
                    ['orden' => 5, 'nombre' => 'Cena', 'hora_objetivo' => '21:00:00'],
                ];
            } else {
                // Fallback generator for N (2, 6, 7)
                for ($i = 1; $i <= $numComidas; $i++) {
                    $hour = 8 + ($i - 1) * 3;
                    $slots[] = [
                        'orden' => $i,
                        'nombre' => 'Comida ' . $i,
                        'hora_objetivo' => sprintf('%02d:00:00', $hour),
                    ];
                }
            }

            // Gym hour window detection (±90 mins)
            $horaGimnasio = $validated['hora_gimnasio'] ?? '18:00:00';
            // standardise to seconds since midnight
            $gymSeconds = $this->timeToSeconds($horaGimnasio);

            foreach ($slots as $slot) {
                $slotSeconds = $this->timeToSeconds($slot['hora_objetivo']);
                $diff = abs($gymSeconds - $slotSeconds);

                $esPre = false;
                $esPost = false;

                if ($diff <= 5400) { // 90 minutes
                    if ($slotSeconds < $gymSeconds) {
                        $esPre = true;
                    } else {
                        $esPost = true;
                    }
                }

                $template->mealSlots()->create([
                    'orden' => $slot['orden'],
                    'nombre' => $slot['nombre'],
                    'hora_objetivo' => $slot['hora_objetivo'],
                    'es_pre_entreno' => $esPre,
                    'es_post_entreno' => $esPost,
                ]);
            }
        });

        // Generate the first plan weekly
        $weeklyPlan = WeeklyPlan::create([
            'user_id'       => $user->id,
            'semana_inicio' => now()->startOfWeek()->toDateString(),
            'status'        => 'pending',
        ]);

        GenerateDietJob::dispatch($weeklyPlan);

        return response()->json([
            'weekly_plan_id' => $weeklyPlan->id,
            'status' => 'pending',
        ], 202);
    }

    public function step3(Step3Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            // Clear existing restrictions
            $user->foodRestrictions()->delete();

            // Insert new restrictions
            if (!empty($validated['restricciones'])) {
                foreach ($validated['restricciones'] as $item) {
                    $user->foodRestrictions()->create([
                        'food_id' => $item['food_id'],
                        'tipo' => $item['tipo'],
                    ]);
                }
            }
        });

        // Regenerate the weekly plan
        $weeklyPlan = WeeklyPlan::create([
            'user_id'       => $user->id,
            'semana_inicio' => now()->startOfWeek()->toDateString(),
            'status'        => 'pending',
        ]);

        GenerateDietJob::dispatch($weeklyPlan);

        return response()->json([
            'weekly_plan_id' => $weeklyPlan->id,
            'status' => 'pending',
        ], 202);
    }

    private function timeToSeconds(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
