<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DECISIONS.md #5 — channel list lives in the DB, phone-editable via
        // the /settings/channels Livewire page.
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channels');
    }
};
