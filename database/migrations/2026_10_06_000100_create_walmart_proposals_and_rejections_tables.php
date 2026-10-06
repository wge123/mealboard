<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An agent's web-search guess, waiting for a human tick. Kept out of
        // walmart_matches on purpose: every reader of that table (the cart
        // link, the row chips, the MCP list) treats a row as confirmed.
        Schema::create('walmart_match_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('product_url', 2048);
            $table->string('product_name');
            $table->string('confidence', 8)->nullable();
            $table->timestamps();
        });

        // Products a human turned down for an ingredient: never proposed again.
        Schema::create('walmart_rejected_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('item_id', 32);
            $table->timestamps();

            $table->unique(['ingredient_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walmart_rejected_items');
        Schema::dropIfExists('walmart_match_proposals');
    }
};
