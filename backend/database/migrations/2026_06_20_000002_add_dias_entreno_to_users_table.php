<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ISO weekday numbers the user trains (1 = Mon … 7 = Sun). Null = derive
            // from nivel_actividad (legacy behaviour).
            $table->json('dias_entreno')->nullable()->after('nivel_actividad');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dias_entreno');
        });
    }
};
