<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovered_videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('channel_id');
            $table->string('title');
            $table->text('description');
            $table->timestamp('published_at');
            $table->string('classification')->nullable(); // likely_recipe | not_recipe
            $table->unsignedTinyInteger('score')->nullable(); // 0-100 fit vs DECISIONS.md #6
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable(); // per-video extraction failure (step 18)
            $table->timestamps();

            $table->index('channel_id');
            $table->index('classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_videos');
    }
};
