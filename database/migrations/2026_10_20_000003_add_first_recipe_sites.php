<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The first two recipe sites of ADR 0004, feed addresses from
     * docs/research/recipe-site-json-ld.md. A migration, not the dev seeder,
     * so production gets them; a site the household already listed (or
     * removed and re-added) is left alone.
     */
    private const SITES = [
        'https://www.loveandlemons.com/feed/' => 'Love and Lemons',
        'https://www.justonecookbook.com/feed/' => 'Just One Cookbook',
    ];

    public function up(): void
    {
        foreach (self::SITES as $feedUrl => $name) {
            if (DB::table('recipe_sites')->where('feed_url', $feedUrl)->doesntExist()) {
                DB::table('recipe_sites')->insert([
                    'feed_url' => $feedUrl,
                    'name' => $name,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('recipe_sites')->whereIn('feed_url', array_keys(self::SITES))->delete();
    }
};
