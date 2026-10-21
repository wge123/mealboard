<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_preferences', function (Blueprint $table) {
            // The most total minutes a dinner may take on a quick night; the
            // default applies to the one existing row too.
            $table->unsignedInteger('quick_night_minutes_limit')->default(20)->after('weekday_ingredient_limit');
        });
    }

    public function down(): void
    {
        Schema::table('household_preferences', function (Blueprint $table) {
            $table->dropColumn('quick_night_minutes_limit');
        });
    }
};
