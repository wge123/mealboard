<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') && ! Auth::check()) {
            $user = User::where('email', 'willem@example.com')->first() ?? User::first();

            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
