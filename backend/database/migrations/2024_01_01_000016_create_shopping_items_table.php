<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->decimal('cantidad_total', 8, 2);
            $table->string('categoria', 50);
            $table->boolean('tengo_en_casa')->default(false);
            $table->boolean('no_lo_quiero')->default(false);
            $table->foreignUuid('sustituido_por_food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_items');
    }
};
