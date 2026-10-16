<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            // The days of a suggested week ticked "Looks good", as YYYY-MM-DD
            // strings shared by the household. They guide Lock, never gate it,
            // and are ignored once the week is locked.
            $table->json('reviewed_dates')->nullable()->after('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn('reviewed_dates');
        });
    }
};
