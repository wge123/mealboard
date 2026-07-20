<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Mealboard') }}</title>
    <script>
        // Apply persisted theme before first paint (system = no attribute).
        (() => {
            const t = localStorage.getItem('mealboard-theme');
            if (t === 'dark') document.documentElement.dataset.theme = 'mealboard-dark';
            else if (t === 'light') document.documentElement.dataset.theme = 'mealboard';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-base-100 text-base-content">
    {{-- Temporary shell — the real navigation arrives in step 4. --}}
    <header class="border-b border-base-300 bg-base-100">
        <nav class="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-x-1 gap-y-2 px-4 py-3">
            <a href="{{ url('/') }}" class="mr-4 font-[family-name:var(--font-display)] text-xl font-semibold text-primary">Mealboard</a>
            @auth
                <div class="flex flex-wrap items-center gap-1">
                    <a href="{{ route('plan.builder') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('plan.*')) btn-active @endif">Plan</a>
                    <a href="{{ route('recipes.index') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('recipes.index') || request()->routeIs('recipes.show') || request()->routeIs('recipes.create')) btn-active @endif">Recipes</a>
                    <a href="{{ route('recipes.approve') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('recipes.approve')) btn-active @endif">Approve</a>
                    <a href="{{ route('log.catch-up') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('log.*')) btn-active @endif">Log</a>
                    <a href="{{ route('insights') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('insights')) btn-active @endif">Insights</a>
                    <a href="{{ route('settings.channels') }}" class="btn btn-ghost btn-sm min-h-11 @if(request()->routeIs('settings.*')) btn-active @endif">Channels</a>
                </div>
            @endauth
            <div class="ms-auto flex items-center gap-2">
                <div
                    x-data="{
                        theme: localStorage.getItem('mealboard-theme') || 'system',
                        set(t) {
                            this.theme = t;
                            if (t === 'system') {
                                localStorage.removeItem('mealboard-theme');
                                document.documentElement.removeAttribute('data-theme');
                            } else {
                                localStorage.setItem('mealboard-theme', t);
                                document.documentElement.dataset.theme = t === 'dark' ? 'mealboard-dark' : 'mealboard';
                            }
                        },
                    }"
                    class="dropdown dropdown-end"
                >
                    <button type="button" tabindex="0" class="btn btn-ghost btn-sm min-h-11" aria-label="Theme">
                        <span x-show="theme === 'light'">Light</span>
                        <span x-show="theme === 'dark'">Dark</span>
                        <span x-show="theme === 'system'">Auto</span>
                    </button>
                    <ul tabindex="0" class="dropdown-content menu z-10 mt-1 w-32 rounded-box border border-base-300 bg-base-100 p-1">
                        <li><button type="button" @click="set('light'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'light' && 'menu-active'">Light</button></li>
                        <li><button type="button" @click="set('dark'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'dark' && 'menu-active'">Dark</button></li>
                        <li><button type="button" @click="set('system'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'system' && 'menu-active'">System</button></li>
                    </ul>
                </div>
                @auth
                    <span class="hidden text-sm opacity-60 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm min-h-11">Log out</button>
                    </form>
                @else
                    <a class="btn btn-outline btn-primary btn-sm min-h-11" href="{{ route('login') }}">Log in</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-16">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
