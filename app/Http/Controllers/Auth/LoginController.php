<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, LoginAction $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->only(['email', 'password']),
            $request->boolean('remember'),
        );

        return redirect()->intended('/');
    }

    public function destroy(LogoutAction $action): RedirectResponse
    {
        $action->handle();

        return redirect('/');
    }
}
