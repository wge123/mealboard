<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A feed link can be longer than the 255 characters a unique varchar holds.
 * The address becomes text and its sha256 hash carries the uniqueness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seen_posts', function (Blueprint $table) {
            $table->char('url_hash', 64)->nullable()->after('id');
        });

        foreach (DB::table('seen_posts')->get(['id', 'url']) as $post) {
            DB::table('seen_posts')->where('id', $post->id)->update(['url_hash' => hash('sha256', $post->url)]);
        }

        Schema::table('seen_posts', function (Blueprint $table) {
            $table->dropUnique(['url']);
        });

        Schema::table('seen_posts', function (Blueprint $table) {
            $table->text('url')->change();
            $table->char('url_hash', 64)->nullable(false)->change();
        });

        Schema::table('seen_posts', function (Blueprint $table) {
            $table->unique('url_hash');
        });
    }

    public function down(): void
    {
        Schema::table('seen_posts', function (Blueprint $table) {
            $table->dropUnique(['url_hash']);
        });

        Schema::table('seen_posts', function (Blueprint $table) {
            $table->dropColumn('url_hash');
        });

        // The varchar(255) unique can only hold addresses that fit it.
        DB::table('seen_posts')->whereRaw('length(url) > 255')->delete();

        Schema::table('seen_posts', function (Blueprint $table) {
            $table->string('url')->unique()->change();
        });
    }
};
