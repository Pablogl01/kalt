<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('semana_inicio');
            $table->timestamp('generado_en')->nullable();
            $table->string('status', 20)->default('pending'); // pending | ready | failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Index required by §3.6 — fast lookup of user's active plan
            $table->index(['user_id', 'semana_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_plans');
    }
};
