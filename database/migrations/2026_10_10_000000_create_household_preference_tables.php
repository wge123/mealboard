<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The household preferences: one row holding the weekday limits and the
 * household size (this is a single-household app, so there is always exactly
 * one row, inserted here with the defaults DECISIONS.md #6 used to hard-code),
 * and the avoided ingredients as words, stored trimmed and lowercased. Words,
 * not ingredient links, because an avoided word must match an ingredient no
 * recipe has used yet. Pantry staples stay the existing ingredient flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('weekday_minutes_limit');
            $table->unsignedInteger('weekday_ingredient_limit');
            $table->unsignedInteger('household_size');
            $table->timestamps();
        });

        Schema::create('avoided_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->timestamps();
        });

        DB::table('household_preferences')->insert([
            'weekday_minutes_limit' => 30,
            'weekday_ingredient_limit' => 10,
            'household_size' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('avoided_ingredients');
        Schema::dropIfExists('household_preferences');
    }
};
