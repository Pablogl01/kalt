<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('day_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('meal_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre', 100);
            $table->time('hora_objetivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
