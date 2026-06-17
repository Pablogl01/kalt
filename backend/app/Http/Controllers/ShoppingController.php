<?php

namespace App\Http\Controllers;

use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Services\ShoppingGenerator;
use App\Services\SubstituteEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingController extends Controller
{
    /**
     * Devuelve la lista de la compra del plan activo del usuario.
     */
    public function active(Request $request): JsonResponse
    {
        $plan = $request->user()->weeklyPlans()
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$plan) {
            return response()->json([
                'message' => 'No active plan found.'
            ], 404);
        }

        $shoppingList = $plan->shoppingList;

        if (!$shoppingList) {
            return response()->json([
                'message' => 'No shopping list found for active plan.',
                'weekly_plan_id' => $plan->id
            ], 404);
        }

        $this->authorize('view', $shoppingList);

        return response()->json(
            $shoppingList->load(['shoppingItems.food', 'shoppingItems.substituteFood'])
        );
    }

    /**
     * Genera o regenera la lista del plan activo.
     */
    public function generate(Request $request, ShoppingGenerator $generator): JsonResponse
    {
        $plan = $request->user()->weeklyPlans()
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$plan) {
            return response()->json([
                'message' => 'No active plan found.'
            ], 404);
        }

        $this->authorize('update', $plan);

        $shoppingList = $generator->generate($plan);

        return response()->json(
            $shoppingList->load(['shoppingItems.food', 'shoppingItems.substituteFood']),
            201
        );
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('view', $shoppingList);

        return response()->json(
            $shoppingList->load(['shoppingItems.food', 'shoppingItems.substituteFood'])
        );
    }

    /**
     * Marca el ítem como tengo_en_casa = true.
     */
    public function have(ShoppingItem $shoppingItem): JsonResponse
    {
        $this->authorize('update', $shoppingItem->shoppingList);

        $shoppingItem->update(['tengo_en_casa' => true]);

        return response()->json($shoppingItem->fresh(['food', 'substituteFood']));
    }

    /**
     * Marca el ítem como no_lo_quiero = true y devuelve sustitutos.
     */
    public function dontWant(ShoppingItem $shoppingItem, SubstituteEngine $engine): JsonResponse
    {
        $this->authorize('update', $shoppingItem->shoppingList);

        $shoppingItem->update(['no_lo_quiero' => true]);

        $substitutes = $engine->getSubstitutes($shoppingItem->food_id, auth()->id());

        return response()->json($substitutes);
    }

    /**
     * Aplica la sustitución en la lista de compra.
     */
    public function substitute(Request $request, ShoppingItem $shoppingItem, SubstituteEngine $engine): JsonResponse
    {
        $this->authorize('update', $shoppingItem->shoppingList);

        $validated = $request->validate([
            'substitute_food_id' => ['required', 'uuid', 'exists:foods,id'],
        ]);

        $substituteFoodId = $validated['substitute_food_id'];

        // Validación de alérgenos
        $restrictedFoodIds = \App\Models\UserFoodRestriction::where('user_id', $request->user()->id)
            ->get()
            ->filter(fn ($r) => in_array($r->tipo, ['alergia', 'intolerancia']))
            ->pluck('food_id')
            ->toArray();

        if (in_array($substituteFoodId, $restrictedFoodIds)) {
            return response()->json([
                'message' => 'Food is restricted for this user.',
                'errors' => [
                    'substitute_food_id' => ['Food is restricted for this user.']
                ]
            ], 422);
        }

        // Validación de que es sustituto válido
        $substituteRel = \App\Models\FoodSubstitute::where('food_id', $shoppingItem->food_id)
            ->where('substitute_food_id', $substituteFoodId)
            ->exists();
        
        if (!$substituteRel) {
            return response()->json([
                'message' => 'Invalid substitute food for this item.',
                'errors' => [
                    'substitute_food_id' => ['Invalid substitute food for this item.']
                ]
            ], 422);
        }

        $shoppingItem->update([
            'sustituido_por_food_id' => $substituteFoodId
        ]);

        return response()->json($shoppingItem->fresh(['food', 'substituteFood']));
    }

    public function update(Request $request, ShoppingItem $shoppingItem): JsonResponse
    {
        $this->authorize('update', $shoppingItem->shoppingList);
        return response()->json($shoppingItem->fresh());
    }
}
