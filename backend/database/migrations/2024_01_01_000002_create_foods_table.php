<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('categoria', 50);
            $table->decimal('calorias', 7, 2);
            $table->decimal('proteina', 6, 2);
            $table->decimal('carbos', 6, 2);
            $table->decimal('grasa', 6, 2);
            $table->boolean('apto_volumen')->default(true);
            $table->boolean('apto_definicion')->default(true);
            $table->boolean('apto_mantenimiento')->default(true);
            // ponytail: solo proteínas y carbos lo necesitan; las grasas valen para cualquier comida
            $table->boolean('apto_desayuno')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
