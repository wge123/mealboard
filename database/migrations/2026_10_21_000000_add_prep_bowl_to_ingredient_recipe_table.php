<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_recipe', function (Blueprint $table) {
            // The prep bowl an ingredient is combined into before cooking
            // ("spice bowl", "sauce"), ADR 0005. Null: prepped on its own.
            $table->string('prep_bowl', 64)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_recipe', function (Blueprint $table) {
            $table->dropColumn('prep_bowl');
        });
    }
};
