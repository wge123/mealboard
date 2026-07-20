<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Mealboard') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @livewireStyles
</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">Mealboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                @auth
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('plan.*')) active @endif" href="{{ route('plan.builder') }}">Plan</a></li>
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('recipes.index') || request()->routeIs('recipes.show') || request()->routeIs('recipes.create')) active @endif" href="{{ route('recipes.index') }}">Recipes</a></li>
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('recipes.approve')) active @endif" href="{{ route('recipes.approve') }}">Approve</a></li>
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('log.*')) active @endif" href="{{ route('log.catch-up') }}">Log</a></li>
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('insights')) active @endif" href="{{ route('insights') }}">Insights</a></li>
                        <li class="nav-item"><a class="nav-link @if(request()->routeIs('settings.*')) active @endif" href="{{ route('settings.channels') }}">Channels</a></li>
                    </ul>
                @endauth
                <div class="d-flex align-items-center gap-3">
                    @auth
                        <span class="text-muted small">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                        </form>
                    @else
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('login') }}">Log in</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="container pb-5">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @livewireScripts
</body>
</html>
