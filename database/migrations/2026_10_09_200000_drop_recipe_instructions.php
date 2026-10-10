<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The free-text method goes: every recipe now has the recipe shape (tools,
 * ingredients, cooking steps). Dropping the column loses the old text, so
 * refuse while any recipe still lacks part of the shape and name those
 * recipes so they can be fixed in the edit form first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unshaped = DB::table('recipes')
            ->where(fn ($q) => $q
                ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('recipe_tools')->whereColumn('recipe_tools.recipe_id', 'recipes.id'))
                ->orWhereNotExists(fn ($s) => $s->select(DB::raw(1))->from('ingredient_recipe')->whereColumn('ingredient_recipe.recipe_id', 'recipes.id'))
                ->orWhereNotExists(fn ($s) => $s->select(DB::raw(1))->from('cooking_steps')->whereColumn('cooking_steps.recipe_id', 'recipes.id')))
            ->orderBy('id')
            ->get(['id', 'title']);

        if ($unshaped->isNotEmpty()) {
            $list = $unshaped->map(fn (object $recipe) => "#{$recipe->id} {$recipe->title}")->implode('; ');

            throw new RuntimeException("Cannot drop the old recipe method: {$unshaped->count()} recipe(s) lack a tool, an ingredient or a cooking step: {$list}. Fix them in the edit form, then migrate again.");
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }

    /**
     * Restores the column empty; the old text is gone.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('instructions')->nullable();
        });
    }
};
