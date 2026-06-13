<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('meal_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('es_extra')->default(false);
            $table->boolean('realizada')->default(false);
            $table->time('hora_real')->nullable();
            $table->timestamps();

            // Index required by §3.6 — frequent join from daily_logs
            $table->index('daily_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
    }
};
