<?php

namespace App\Jobs;

use App\Models\ShoppingList;
use App\Models\WeeklyPlan;
use App\Services\ShoppingGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds a plan's shopping list off the request thread, so actions that change
 * meal items (substitute, regenerate) return immediately instead of waiting for
 * the (re)generation.
 */
class RegenerateShoppingListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly WeeklyPlan $weeklyPlan) {}

    public function handle(ShoppingGenerator $generator): void
    {
        if (ShoppingList::where('weekly_plan_id', $this->weeklyPlan->id)->exists()) {
            $generator->regenerate($this->weeklyPlan);
        } else {
            $generator->generate($this->weeklyPlan);
        }
    }
}
