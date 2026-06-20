<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDietJob;
use App\Models\Food;
use App\Models\WeeklyPlan;
use App\Services\DietCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplementController extends Controller
{
    public function __construct(private readonly DietCalculator $calculator) {}

    /**
     * The authenticated user's supplements.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->supplements()->with('food')->get()
        );
    }

    /**
     * Foods that can be picked as supplements (whey + dedicated supplement foods).
     */
    public function catalog(): JsonResponse
    {
        $foods = Food::where('categoria', 'suplemento')
            ->orWhere('nombre', 'Proteína de suero en polvo (Whey)')
            ->orderBy('nombre')
            ->get();

        return response()->json($foods);
    }

    /**
     * Replace the user's supplement set, then regenerate the active plan since
     * macro-affecting supplements change how meals are built.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplements'                 => ['present', 'array'],
            'supplements.*.food_id'       => ['required', 'uuid', 'exists:foods,id'],
            'supplements.*.dosis_gramos'  => ['required', 'numeric', 'min:0', 'max:1000'],
            'supplements.*.momento'       => ['required', 'in:post_entreno,pre_entreno,desayuno,cena,diario'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {
            $user->supplements()->delete();

            foreach ($validated['supplements'] as $item) {
                $food = Food::find($item['food_id']);
                // A supplement counts towards macros only if its food carries any.
                $afectaMacros = $food
                    && ($food->proteina + $food->carbos + $food->grasa) > 0;

                $user->supplements()->create([
                    'food_id'       => $item['food_id'],
                    'dosis_gramos'  => $item['dosis_gramos'],
                    'momento'       => $item['momento'],
                    'afecta_macros' => $afectaMacros,
                ]);
            }
        });

        $response = ['supplements' => $user->supplements()->with('food')->get()];

        // Regenerate the plan so the new supplement set is reflected.
        if ($this->calculator->userHasCompleteProfile($user)) {
            $plan = WeeklyPlan::create([
                'user_id'       => $user->id,
                'semana_inicio' => now()->startOfWeek()->toDateString(),
                'status'        => 'pending',
            ]);
            GenerateDietJob::dispatch($plan);
            $response['weekly_plan_id'] = $plan->id;
        }

        return response()->json($response);
    }
}
