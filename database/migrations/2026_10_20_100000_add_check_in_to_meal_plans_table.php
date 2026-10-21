<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            // The week check-in answers, shared by the household and kept for
            // the plan's lifetime: {"skipped": [{"date": "YYYY-MM-DD", "slot": "lunch"}]}.
            // Later answers (quick nights, a craving) are further keys.
            $table->json('check_in')->nullable()->after('reviewed_dates');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn('check_in');
        });
    }
};
