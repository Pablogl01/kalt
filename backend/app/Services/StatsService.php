<?php

namespace App\Services;

use App\Models\User;
use App\Models\DailyLog;
use App\Models\DayPlan;
use App\Models\UserWeightLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StatsService
{
    public function getMacroStats(User $user, Carbon $from, Carbon $to): array
    {
        $this->validateRange($from, $to);

        $cacheKey = "stats:{$user->id}:macros:{$from->format('Y-m-d')}:{$to->format('Y-m-d')}";
        $this->trackCacheKey($user->id, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($user, $from, $to) {
            $dayPlans = DayPlan::whereHas('weeklyPlan', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get()
                ->keyBy(fn($plan) => $plan->fecha->format('Y-m-d'));

            $aggregates = \Illuminate\Support\Facades\DB::table('daily_logs')
                ->join('meal_logs', 'daily_logs.id', '=', 'meal_logs.daily_log_id')
                ->join('meal_log_items', 'meal_logs.id', '=', 'meal_log_items.meal_log_id')
                ->where('daily_logs.user_id', $user->id)
                ->whereBetween('daily_logs.fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->where('meal_logs.realizada', true)
                ->selectRaw('daily_logs.fecha, SUM(meal_log_items.calorias) as real_cal, SUM(meal_log_items.proteina) as real_prot, SUM(meal_log_items.carbos) as real_carb, SUM(meal_log_items.grasa) as real_fat')
                ->groupBy('daily_logs.fecha')
                ->get()
                ->keyBy(fn($row) => Carbon::parse($row->fecha)->format('Y-m-d'));

            $result = [];
            $curr = $from->copy();
            while ($curr->lte($to)) {
                $dateStr = $curr->format('Y-m-d');
                $plan = $dayPlans->get($dateStr);
                $agg = $aggregates->get($dateStr);

                $result[] = [
                    'fecha'             => $dateStr,
                    'calorias_reales'   => $agg ? round($agg->real_cal, 2) : 0,
                    'calorias_objetivo' => $plan ? (float)$plan->calorias_objetivo : 0.0,
                    'proteina_real'     => $agg ? round($agg->real_prot, 2) : 0,
                    'proteina_objetivo' => $plan ? (float)$plan->proteina_obj : 0.0,
                    'carbos_real'       => $agg ? round($agg->real_carb, 2) : 0,
                    'carbos_objetivo'   => $plan ? (float)$plan->carbos_obj : 0.0,
                    'grasa_real'        => $agg ? round($agg->real_fat, 2) : 0,
                    'grasa_objetivo'    => $plan ? (float)$plan->grasa_obj : 0.0,
                ];

                $curr->addDay();
            }

            return $result;
        });
    }

    public function getAdherenceStats(User $user, Carbon $from, Carbon $to): array
    {
        $this->validateRange($from, $to);

        $cacheKey = "stats:{$user->id}:adherence:{$from->format('Y-m-d')}:{$to->format('Y-m-d')}";
        $this->trackCacheKey($user->id, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($user, $from, $to) {
            $dailyLogs = DailyLog::where('user_id', $user->id)
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->withCount(['mealLogs as total_planned' => function ($q) {
                    $q->where('es_extra', false);
                }, 'mealLogs as completed_planned' => function ($q) {
                    $q->where('es_extra', false)->where('realizada', true);
                }])
                ->get()
                ->keyBy(fn($log) => $log->fecha->format('Y-m-d'));

            $result = [];
            $curr = $from->copy();
            while ($curr->lte($to)) {
                $dateStr = $curr->format('Y-m-d');
                $log = $dailyLogs->get($dateStr);

                if (!$log) {
                    $level = 'sin_datos';
                } else {
                    $trainingOk = !$log->entreno_planificado || $log->ha_entrenado;
                    $mealsOk = $log->total_planned === 0 || $log->total_planned === $log->completed_planned;

                    if ($trainingOk && $mealsOk) {
                        $level = 'completa';
                    } else {
                        $level = 'parcial';
                    }
                }

                $result[] = [
                    'fecha' => $dateStr,
                    'nivel' => $level,
                    'entreno_planificado' => $log ? (bool)$log->entreno_planificado : null,
                    'ha_entrenado' => $log ? (bool)$log->ha_entrenado : null,
                ];

                $curr->addDay();
            }

            return $result;
        });
    }

    public function getHeatmapStats(User $user, Carbon $from, Carbon $to): array
    {
        $this->validateRange($from, $to);

        $cacheKey = "stats:{$user->id}:heatmap:{$from->format('Y-m-d')}:{$to->format('Y-m-d')}";
        $this->trackCacheKey($user->id, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($user, $from, $to) {
            $dailyLogs = DailyLog::where('user_id', $user->id)
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->withCount(['mealLogs as total_planned' => function ($q) {
                    $q->where('es_extra', false);
                }, 'mealLogs as completed_planned' => function ($q) {
                    $q->where('es_extra', false)->where('realizada', true);
                }])
                ->get()
                ->keyBy(fn($log) => $log->fecha->format('Y-m-d'));

            $aggregates = \Illuminate\Support\Facades\DB::table('daily_logs')
                ->join('meal_logs', 'daily_logs.id', '=', 'meal_logs.daily_log_id')
                ->join('meal_log_items', 'meal_logs.id', '=', 'meal_log_items.meal_log_id')
                ->where('daily_logs.user_id', $user->id)
                ->whereBetween('daily_logs.fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->where('meal_logs.realizada', true)
                ->selectRaw('daily_logs.fecha, SUM(meal_log_items.calorias) as real_cal')
                ->groupBy('daily_logs.fecha')
                ->get()
                ->keyBy(fn($row) => Carbon::parse($row->fecha)->format('Y-m-d'));

            $dayPlans = DayPlan::whereHas('weeklyPlan', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get()
                ->keyBy(fn($plan) => $plan->fecha->format('Y-m-d'));

            $adherenceResult = [];
            $macrosResult = [];
            $curr = $from->copy();

            while ($curr->lte($to)) {
                $dateStr = $curr->format('Y-m-d');
                $log = $dailyLogs->get($dateStr);
                $agg = $aggregates->get($dateStr);
                $plan = $dayPlans->get($dateStr);

                if (!$log) {
                    $level = 'sin_datos';
                } else {
                    $trainingOk = !$log->entreno_planificado || $log->ha_entrenado;
                    $mealsOk = $log->total_planned === 0 || $log->total_planned === $log->completed_planned;

                    if ($trainingOk && $mealsOk) {
                        $level = 'completa';
                    } else {
                        $level = 'parcial';
                    }
                }

                $adherenceResult[] = [
                    'fecha' => $dateStr,
                    'nivel' => $level,
                    'entreno_planificado' => $log ? (bool)$log->entreno_planificado : null,
                    'ha_entrenado' => $log ? (bool)$log->ha_entrenado : null,
                ];

                $macrosResult[] = [
                    'fecha'             => $dateStr,
                    'calorias_reales'   => $agg ? round($agg->real_cal, 2) : 0,
                    'calorias_objetivo' => $plan ? (float)$plan->calorias_objetivo : 0.0,
                ];

                $curr->addDay();
            }

            return [
                'adherence' => $adherenceResult,
                'macros'    => $macrosResult,
            ];
        });
    }

    public function getTrainingStats(User $user, Carbon $from, Carbon $to): array
    {
        $this->validateRange($from, $to);

        $cacheKey = "stats:{$user->id}:training:{$from->format('Y-m-d')}:{$to->format('Y-m-d')}";
        $this->trackCacheKey($user->id, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($user, $from, $to) {
            $logs = DailyLog::where('user_id', $user->id)
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get();

            $diasPlanificados = $logs->where('entreno_planificado', true)->count();
            $diasEntrenados = $logs->where('entreno_planificado', true)->where('ha_entrenado', true)->count();
            $diasExtra = $logs->where('entreno_planificado', false)->where('ha_entrenado', true)->count();

            // Calculate streaks historically
            $trainedDates = DailyLog::where('user_id', $user->id)
                ->where('ha_entrenado', true)
                ->orderBy('fecha', 'asc')
                ->pluck('fecha')
                ->map(fn($d) => $d->format('Y-m-d'))
                ->toArray();
            $mejorRacha = 0;
            $currentStreak = 0;
            $lastDate = null;
            $streaks = [];

            foreach ($trainedDates as $dateStr) {
                $date = Carbon::parse($dateStr)->startOfDay();
                if ($lastDate === null) {
                    $currentStreak = 1;
                } else {
                    $diff = (int)$lastDate->diffInDays($date);
                    if ($diff === 1) {
                        $currentStreak++;
                    } elseif ($diff > 1) {
                        $streaks[] = $currentStreak;
                        $currentStreak = 1;
                    }
                }
                $lastDate = $date;
            }

            if ($currentStreak > 0) {
                $streaks[] = $currentStreak;
            }

            $mejorRacha = count($streaks) > 0 ? max($streaks) : 0;

            $rachaActual = 0;
            if ($lastDate !== null) {
                $today = Carbon::today()->startOfDay();
                $diffToToday = (int)$lastDate->diffInDays($today);
                if ($diffToToday <= 1) {
                    $rachaActual = $currentStreak;
                }
            }

            return [
                'dias_planificados' => $diasPlanificados,
                'dias_entrenados'   => $diasEntrenados,
                'dias_extra'        => $diasExtra,
                'racha_actual'      => $rachaActual,
                'mejor_racha'       => $mejorRacha,
            ];
        });
    }

    public function getWeightStats(User $user, Carbon $from, Carbon $to): array
    {
        $this->validateRange($from, $to);

        $cacheKey = "stats:{$user->id}:weight:{$from->format('Y-m-d')}:{$to->format('Y-m-d')}";
        $this->trackCacheKey($user->id, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($user, $from, $to) {
            $logs = UserWeightLog::where('user_id', $user->id)
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orderBy('fecha', 'asc')
                ->get();

            return $logs->map(fn($log) => [
                'fecha' => $log->fecha->format('Y-m-d'),
                'peso'  => (float)$log->peso,
                'nota'  => $log->nota,
            ])->toArray();
        });
    }

    protected function validateRange(Carbon $from, Carbon $to): void
    {
        if ($from->diffInDays($to) > 365) {
            throw new \InvalidArgumentException('El rango de fechas no puede ser superior a 365 días.');
        }
    }

    protected function trackCacheKey(string $userId, string $cacheKey): void
    {
        $keysKey = "stats:keys:{$userId}";
        $existingKeys = Cache::get($keysKey, []);
        if (!in_array($cacheKey, $existingKeys)) {
            $existingKeys[] = $cacheKey;
            Cache::forever($keysKey, $existingKeys);
        }
    }

    public static function clearCache(string $userId): void
    {
        $keysKey = "stats:keys:{$userId}";
        $keys = Cache::get($keysKey, []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($keysKey);
    }
}
