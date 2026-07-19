<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mapping memory for the Walmart handoff: one remembered product per
        // ingredient, refreshed each time the user re-confirms it.
        Schema::create('walmart_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('product_url', 2048);
            $table->string('product_name');
            $table->timestamp('last_confirmed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walmart_matches');
    }
};
