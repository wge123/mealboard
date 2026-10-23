<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the last cart import reported for the product; null = never seen.
        Schema::table('walmart_matches', function (Blueprint $table) {
            $table->string('availability')->nullable()->after('product_name');
            $table->timestamp('availability_seen_at')->nullable()->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('walmart_matches', function (Blueprint $table) {
            $table->dropColumn(['availability', 'availability_seen_at']);
        });
    }
};
