<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->boolean('entreno_planificado')->default(false);
            $table->boolean('ha_entrenado')->nullable();
            $table->time('hora_gimnasio')->nullable();
            $table->string('tipo_sesion', 20)->nullable();
            $table->string('recalculo_motivo', 50)->nullable();
            $table->timestamps();

            // Index required by §3.6 — primary query executed multiple times per day
            $table->index(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
