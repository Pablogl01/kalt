<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meal_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('nombre', 100);
            $table->time('hora_objetivo')->nullable();
            $table->boolean('es_pre_entreno')->default(false);
            $table->boolean('es_post_entreno')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_slots');
    }
};
