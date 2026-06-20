<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_supplements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->decimal('dosis_gramos', 6, 2)->default(0);
            // Cuándo se toma: define en qué comida se inyecta si afecta a macros.
            $table->string('momento', 20)->default('post_entreno');
            // Si true, cuenta en los macros (se inyecta como alimento fijo en una comida).
            $table->boolean('afecta_macros')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'food_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_supplements');
    }
};
