<?php

namespace App\Http\Controllers;

use App\Models\MealItem;
use App\Services\SubstituteEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubstituteController extends Controller
{
    protected SubstituteEngine $substituteEngine;

    public function __construct(SubstituteEngine $substituteEngine)
    {
        $this->substituteEngine = $substituteEngine;
    }

    public function substitutes(MealItem $mealItem, Request $request): JsonResponse
    {
        $this->authorize('view', $mealItem);

        $substitutes = $this->substituteEngine->getSubstitutes($mealItem->food_id, $request->user()->id);

        return response()->json($substitutes);
    }

    public function substitute(MealItem $mealItem, Request $request): JsonResponse
    {
        $this->authorize('update', $mealItem);

        $validated = $request->validate([
            'substitute_food_id' => ['required', 'uuid', 'exists:foods,id'],
        ]);

        try {
            $updatedItem = $this->substituteEngine->applySubstitute(
                $mealItem,
                $validated['substitute_food_id'],
                $request->user()->id
            );

            return response()->json($updatedItem->load('food'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'substitute_food_id' => [$e->getMessage()]
                ]
            ], 422);
        }
    }
}
