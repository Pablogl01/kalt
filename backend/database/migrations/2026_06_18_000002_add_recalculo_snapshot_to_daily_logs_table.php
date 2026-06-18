<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            // Snapshot of the last automatic recalculation, used to undo it.
            // Stores the previous quantities/macros of the affected meal items.
            $table->jsonb('recalculo_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropColumn('recalculo_snapshot');
        });
    }
};
