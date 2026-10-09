<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recipe shape (expand step): tools with alternatives, and cooking steps.
 * The prep note is the existing ingredient_recipe.note column. The old
 * free-text method becomes nullable and stays until the contract migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();
        });

        Schema::create('recipe_tool_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_tool_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('word');
            // Empty for a word the inventory cannot resolve (the unknown kind).
            $table->foreignId('kitchen_tool_kind_id')->nullable()->constrained('kitchen_tool_kinds')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cooking_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('text');
            $table->timestamps();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->text('instructions')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('instructions')->nullable(false)->change();
        });

        Schema::dropIfExists('cooking_steps');
        Schema::dropIfExists('recipe_tool_alternatives');
        Schema::dropIfExists('recipe_tools');
    }
};
