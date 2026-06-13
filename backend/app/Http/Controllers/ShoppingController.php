<?php

namespace App\Http\Controllers;

use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingController extends Controller
{
    public function show(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('view', $shoppingList);

        return response()->json(
            $shoppingList->load(['shoppingItems.food', 'shoppingItems.substituteFood'])
        );
    }

    public function update(Request $request, ShoppingItem $shoppingItem): JsonResponse
    {
        // Implemented in Phase 5
        return response()->json($shoppingItem->fresh());
    }
}
