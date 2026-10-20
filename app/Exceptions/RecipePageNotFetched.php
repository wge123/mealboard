<?php

namespace App\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * A recipe page or feed the site would not give the app: a non-200, a
 * bot-wall challenge or a timeout. The message says which, with the address.
 */
class RecipePageNotFetched extends RuntimeException
{
    public static function answered(string $url, int $status): self
    {
        return new self("Could not fetch {$url}: the site answered {$status}");
    }

    public static function challenged(string $url): self
    {
        return new self("Could not fetch {$url}: the site answered with a bot-wall challenge");
    }

    public static function timedOut(string $url, ConnectionException $previous): self
    {
        return new self("Could not fetch {$url}: {$previous->getMessage()}", previous: $previous);
    }
}
