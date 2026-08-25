<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('recipe_request_id')->nullable()->after('source')
                ->constrained()->nullOnDelete();
        });

        Schema::table('discovered_videos', function (Blueprint $table) {
            $table->foreignId('recipe_request_id')->nullable()->after('channel_id')
                ->constrained()->nullOnDelete();

            // A search hit carries neither field: yt-dlp's flat search listing
            // returns null for both, and inventing a description or stamping
            // published_at with now() would put fabricated data in the column
            // the classifier reads. Channel-polled rows still fill both.
            $table->text('description')->nullable()->change();
            $table->timestamp('published_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_request_id');
        });

        Schema::table('discovered_videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_request_id');
            $table->text('description')->nullable(false)->change();
            $table->timestamp('published_at')->nullable(false)->change();
        });
    }
};
