<?php

namespace App\Http\Controllers;

use App\Models\UserWeightLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Implemented in Phase 6
        return response()->json([]);
    }

    public function storeWeight(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'peso' => ['required', 'numeric', 'min:20', 'max:500'],
            'nota' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
        ]);

        $log = UserWeightLog::create([
            'user_id' => $request->user()->id,
            'fecha'   => $validated['fecha'],
            'peso'    => $validated['peso'],
            'nota'    => $validated['nota'] ?? null,
        ]);

        return response()->json($log, 201);
    }
}
