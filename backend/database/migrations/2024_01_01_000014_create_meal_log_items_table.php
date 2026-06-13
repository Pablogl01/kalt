<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_log_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meal_log_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->decimal('cantidad_gramos', 7, 2);
            $table->decimal('calorias', 7, 2);
            $table->decimal('proteina', 6, 2);
            $table->decimal('carbos', 6, 2);
            $table->decimal('grasa', 6, 2);
            $table->timestamps();

            // Index required by §3.6 — daily tracking load join
            $table->index('meal_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_log_items');
    }
};
