<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            // Stamped by the MCP mark_list_purchased tool once the week's
            // Walmart order is actually placed.
            $table->timestamp('purchased_at')->nullable()->after('checked_items');
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn('purchased_at');
        });
    }
};
