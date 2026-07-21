<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every recipe needs a source (user decision 2026-07-21): a link to the
 * recipe page or video it came from / was inspired by. Existing rows were
 * backfilled with verified URLs before this constraint; the migration fails
 * loudly if any null slipped through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->string('source_url')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->change();
        });
    }
};
