<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 8, 2)->nullable();
            $table->string('unit', 16)->nullable(); // g,kg,ml,l,tsp,tbsp,cup,oz,lb,count
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['ingredient_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_recipe');
    }
};
