<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_requests', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->string('status')->default('pending'); // pending | running | completed | failed
            $table->unsignedSmallInteger('candidates_found')->default(0);
            // Lane failures, one line each. A request whose lanes ALL failed is
            // status=failed; a partial failure still completes and keeps the
            // text here so the page can say which half went missing.
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_requests');
    }
};
