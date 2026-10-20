<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A recipe-site post address the daily discovery has fetched, whatever came of
 * it, so the page is never fetched twice. The address is text of any length;
 * its sha256 hash is what is unique and what lookups go by.
 */
class SeenPost extends Model
{
    protected $fillable = ['url'];

    protected static function booted(): void
    {
        static::saving(fn (self $post) => $post->url_hash = self::hashOf($post->url));
    }

    public static function hashOf(string $url): string
    {
        return hash('sha256', $url);
    }

    /** Remember an address; remembering one twice changes nothing. */
    public static function remember(string $url): self
    {
        return self::query()->where('url_hash', self::hashOf($url))->first() ?? self::create(['url' => $url]);
    }

    /**
     * The given addresses that an earlier run fetched.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public static function seenAmong(array $urls): array
    {
        $seen = self::query()->whereIn('url_hash', array_map(self::hashOf(...), $urls))->pluck('url_hash')->flip();

        return array_values(array_filter($urls, fn (string $url) => $seen->has(self::hashOf($url))));
    }
}
