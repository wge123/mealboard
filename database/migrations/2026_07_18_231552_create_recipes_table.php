<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('source_url')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('pending');
            $table->string('meal_type')->default('any');
            $table->unsignedSmallInteger('prep_minutes');
            $table->unsignedSmallInteger('cook_minutes');
            $table->unsignedSmallInteger('servings');
            $table->text('instructions');
            $table->string('cuisine')->nullable();
            $table->json('tags');
            $table->string('image_url')->nullable(); // reserved, unused
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('meal_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
