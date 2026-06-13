<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('weekly_plan_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('calorias_objetivo', 7, 2);
            $table->decimal('proteina_obj', 6, 2);
            $table->decimal('carbos_obj', 6, 2);
            $table->decimal('grasa_obj', 6, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_plans');
    }
};
