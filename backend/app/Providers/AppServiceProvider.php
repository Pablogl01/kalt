<?php

namespace App\Providers;

use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\MealItem;
use App\Models\ShoppingList;
use App\Models\WeeklyPlan;
use App\Policies\DailyLogPolicy;
use App\Policies\MealLogPolicy;
use App\Policies\MealItemPolicy;
use App\Policies\ShoppingListPolicy;
use App\Policies\WeeklyPlanPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Policies
        Gate::policy(WeeklyPlan::class, WeeklyPlanPolicy::class);
        Gate::policy(DailyLog::class, DailyLogPolicy::class);
        Gate::policy(ShoppingList::class, ShoppingListPolicy::class);
        Gate::policy(MealLog::class, MealLogPolicy::class);
        Gate::policy(MealItem::class, MealItemPolicy::class);
        Gate::policy(\App\Models\UserWeightLog::class, \App\Policies\UserWeightLogPolicy::class);
    }
}
