<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            // Suitable as the centre of a main meal (almuerzo/cena). False for
            // breakfast/snack-only foods (protein powder, oats, rice cakes…) so
            // they don't land in lunch/dinner. Default true: most foods qualify.
            $table->boolean('apto_principal')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('apto_principal');
        });
    }
};
