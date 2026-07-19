<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local mirror of second-brain vault preference notes (brain:sync).
        Schema::create('brain_notes', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->text('content');
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brain_notes');
    }
};
