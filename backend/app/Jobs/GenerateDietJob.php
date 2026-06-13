<?php

namespace App\Jobs;

use App\Models\WeeklyPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDietJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly WeeklyPlan $weeklyPlan
    ) {}

    /**
     * Execute the job — implements Phase 2.
     */
    public function handle(): void
    {
        // TODO: Phase 2 — DietCalculator + MealDistributor logic
        // For now, mark as failed to test the status endpoint
        Log::info('GenerateDietJob dispatched for plan: ' . $this->weeklyPlan->id);
    }

    public function failed(\Throwable $exception): void
    {
        $this->weeklyPlan->update(['status' => 'failed']);
        Log::error('GenerateDietJob failed: ' . $exception->getMessage());
    }
}
