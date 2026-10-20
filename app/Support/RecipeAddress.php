<?php

namespace App\Support;

/**
 * The address of a recipe page as the app files it: without the fragment and
 * without tracking query parameters, which name the visit and not the page.
 * One definition for the import of a pasted link and recipe-site discovery.
 */
final class RecipeAddress
{
    /** Query parameter names that track a visit, exactly or by prefix (utm_*). */
    private const TRACKING = ['fbclid', 'gclid', 'dclid', 'msclkid', 'igshid', 'mc_cid', 'mc_eid', 'ref_src', '_hsenc', '_hsmi'];

    private const TRACKING_PREFIXES = ['utm_'];

    public static function clean(string $url): string
    {
        $url = explode('#', trim($url))[0];

        if (! str_contains($url, '?')) {
            return $url;
        }

        [$base, $query] = explode('?', $url, 2);

        $kept = array_filter(
            explode('&', $query),
            fn (string $pair) => $pair !== '' && ! self::isTracking(strtolower(urldecode(explode('=', $pair, 2)[0]))),
        );

        return $kept === [] ? $base : $base.'?'.implode('&', $kept);
    }

    /** Whether the text is an absolute http(s) address. */
    public static function isWebAddress(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private static function isTracking(string $name): bool
    {
        if (in_array($name, self::TRACKING, true)) {
            return true;
        }

        foreach (self::TRACKING_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
