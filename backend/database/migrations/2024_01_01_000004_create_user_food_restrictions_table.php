<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_food_restrictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->text('tipo'); // encrypted: alergia | intolerancia | no_me_gusta
            $table->timestamps();

            // Index required by §3.6 — consulted in every plan generation and substitution
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_food_restrictions');
    }
};
