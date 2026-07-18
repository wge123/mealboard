<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            // Shopping-list checkbox state, shared by both users. Keys are
            // stable "name|unit" strings.
            $table->json('checked_items')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn('checked_items');
        });
    }
};
