<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GLOSSARY.md: Recipe site — the list lives in the DB, phone-editable
        // from the Settings page, and stays out of brain sync like channels.
        Schema::create('recipe_sites', function (Blueprint $table) {
            $table->id();
            $table->string('feed_url')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_sites');
    }
};
