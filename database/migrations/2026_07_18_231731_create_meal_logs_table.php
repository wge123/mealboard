<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('ate_it');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['planned_meal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
    }
};
