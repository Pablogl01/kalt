<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeightLogRequest;
use App\Models\UserWeightLog;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    protected StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function macros(Request $request): JsonResponse
    {
        $dates = $this->getDates($request);
        if ($dates instanceof JsonResponse) {
            return $dates;
        }

        $stats = $this->statsService->getMacroStats(
            $request->user(),
            $dates['from'],
            $dates['to']
        );

        return response()->json($stats);
    }

    public function adherence(Request $request): JsonResponse
    {
        $dates = $this->getDates($request);
        if ($dates instanceof JsonResponse) {
            return $dates;
        }

        $stats = $this->statsService->getAdherenceStats(
            $request->user(),
            $dates['from'],
            $dates['to']
        );

        return response()->json($stats);
    }

    public function training(Request $request): JsonResponse
    {
        $dates = $this->getDates($request);
        if ($dates instanceof JsonResponse) {
            return $dates;
        }

        $stats = $this->statsService->getTrainingStats(
            $request->user(),
            $dates['from'],
            $dates['to']
        );

        return response()->json($stats);
    }

    public function weight(Request $request): JsonResponse
    {
        $dates = $this->getDates($request);
        if ($dates instanceof JsonResponse) {
            return $dates;
        }

        $stats = $this->statsService->getWeightStats(
            $request->user(),
            $dates['from'],
            $dates['to']
        );

        return response()->json($stats);
    }

    public function indexWeight(Request $request): JsonResponse
    {
        $logs = UserWeightLog::where('user_id', $request->user()->id)
            ->orderBy('fecha', 'desc')
            ->paginate(15);

        return response()->json($logs);
    }

    public function storeWeight(StoreWeightLogRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $log = UserWeightLog::create([
            'user_id' => $request->user()->id,
            'fecha'   => $validated['fecha'],
            'peso'    => $validated['peso'],
            'nota'    => $validated['nota'] ?? null,
        ]);

        return response()->json($log, 201);
    }

    protected function getDates(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $from = isset($validated['from']) 
            ? Carbon::parse($validated['from'])->startOfDay() 
            : Carbon::now()->subDays(29)->startOfDay();

        $to = isset($validated['to']) 
            ? Carbon::parse($validated['to'])->endOfDay() 
            : Carbon::now()->endOfDay();

        if ($from->diffInDays($to) > 365) {
            return response()->json([
                'message' => 'El rango de fechas no puede ser superior a 365 días.'
            ], 422);
        }

        return [
            'from' => $from,
            'to'   => $to,
        ];
    }
}
