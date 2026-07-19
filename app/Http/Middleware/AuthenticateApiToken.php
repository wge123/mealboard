<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Static-token gate for the pull endpoints (DECISIONS.md #7 pull half).
 * Accepts `Authorization: Bearer <token>` or `?token=<token>` — calendar
 * subscribers can't set headers.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('mealboard.api_token');

        if (! is_string($expected) || $expected === '') {
            throw new RuntimeException(
                'MEALBOARD_API_TOKEN not set — add it to .env before exposing the pull endpoints.',
            );
        }

        $given = $request->bearerToken() ?? $request->query('token');

        if (! is_string($given) || ! hash_equals($expected, $given)) {
            abort(401, 'Invalid or missing API token.');
        }

        return $next($request);
    }
}
