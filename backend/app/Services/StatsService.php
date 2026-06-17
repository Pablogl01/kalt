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
            $dailyLogs = DailyLog::where('user_id', $user->id)
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->with(['mealLogs' => function ($q) {
                    $q->where('realizada', true)->with('mealLogItems');
                }])
                ->get()
                ->keyBy(fn($log) => $log->fecha->format('Y-m-d'));

            $dayPlans = DayPlan::whereHas('weeklyPlan', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereBetween('fecha', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->get()
                ->keyBy(fn($plan) => $plan->fecha->format('Y-m-d'));

            $result = [];
            $curr = $from->copy();
            while ($curr->lte($to)) {
                $dateStr = $curr->format('Y-m-d');
                $log = $dailyLogs->get($dateStr);
                $plan = $dayPlans->get($dateStr);

                $realCal = 0;
                $realProt = 0;
                $realCarb = 0;
                $realFat = 0;

                if ($log) {
                    foreach ($log->mealLogs as $mealLog) {
                        foreach ($mealLog->mealLogItems as $item) {
                            $realCal += $item->calorias;
                            $realProt += $item->proteina;
                            $realCarb += $item->carbos;
                            $realFat += $item->grasa;
                        }
                    }
                }

                $result[] = [
                    'fecha'             => $dateStr,
                    'calorias_reales'   => round($realCal, 2),
                    'calorias_objetivo' => $plan ? (float)$plan->calorias_objetivo : 0.0,
                    'proteina_real'     => round($realProt, 2),
                    'proteina_objetivo' => $plan ? (float)$plan->proteina_obj : 0.0,
                    'carbos_real'       => round($realCarb, 2),
                    'carbos_objetivo'   => $plan ? (float)$plan->carbos_obj : 0.0,
                    'grasa_real'        => round($realFat, 2),
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
                ->with(['mealLogs'])
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
                    $mealsPlanned = $log->mealLogs->where('es_extra', false);
                    $mealsOk = $mealsPlanned->count() === 0 || $mealsPlanned->every(fn($m) => $m->realizada);

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
