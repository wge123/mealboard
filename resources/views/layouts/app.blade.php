<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
<body
    class="min-h-screen bg-base-100 text-base-content"
    x-data="{
        theme: localStorage.getItem('mealboard-theme') || 'system',
        setTheme(t) {
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
>
    {{-- Desktop top navbar (lg+ only; phones get the bottom tab bar). --}}
    <header class="hidden border-b border-base-300 bg-base-100 lg:block">
        <nav class="mx-auto flex w-full max-w-6xl items-center gap-1 px-6 py-3">
            <a href="{{ url('/') }}" class="mr-6 font-[family-name:var(--font-display)] text-2xl font-semibold text-primary">Mealboard</a>
            @auth
                <div class="flex items-center gap-1">
                    <a href="{{ route('plan.builder') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('plan.builder') ? 'text-primary' : '' }}">Plan</a>
                    <a href="{{ route('recipes.index') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('recipes.index', 'recipes.show', 'recipes.create') ? 'text-primary' : '' }}">Recipes</a>
                    <a href="{{ route('recipes.request') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('recipes.request') ? 'text-primary' : '' }}">Request</a>
                    <a href="{{ route('recipes.approve') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('recipes.approve') ? 'text-primary' : '' }}">
                        Approve
                        @if ($pendingRecipeCount > 0)
                            <span class="badge badge-primary badge-sm">{{ $pendingRecipeCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('log.catch-up') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('log.*') ? 'text-primary' : '' }}">Log</a>
                    <a href="{{ route('insights') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('insights') ? 'text-primary' : '' }}">Insights</a>
                    <a href="{{ route('settings.channels') }}" class="btn btn-ghost btn-sm min-h-11 {{ request()->routeIs('settings.*') ? 'text-primary' : '' }}">Channels</a>
                </div>
            @endauth
            <div class="ms-auto flex items-center gap-2">
                <div class="dropdown dropdown-end">
                    <button type="button" tabindex="0" class="btn btn-ghost btn-sm min-h-11" aria-label="Theme">
                        <span x-show="theme === 'light'">Light</span>
                        <span x-show="theme === 'dark'">Dark</span>
                        <span x-show="theme === 'system'">Auto</span>
                    </button>
                    <ul tabindex="0" class="dropdown-content menu z-30 mt-1 w-32 rounded-box border border-base-300 bg-base-100 p-1">
                        <li><button type="button" @click="setTheme('light'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'light' && 'menu-active'">Light</button></li>
                        <li><button type="button" @click="setTheme('dark'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'dark' && 'menu-active'">Dark</button></li>
                        <li><button type="button" @click="setTheme('system'); $el.closest('.dropdown').querySelector('button').blur()" :class="theme === 'system' && 'menu-active'">System</button></li>
                    </ul>
                </div>
                @auth
                    <span class="text-sm opacity-60">{{ auth()->user()->name }}</span>
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

    <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-24 lg:px-6 lg:pb-12">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- Mobile bottom tab bar (thumb-reach nav; hidden on lg+). --}}
    @auth
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-base-300 bg-base-100 lg:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
            aria-label="Primary"
        >
            <div class="mx-auto flex w-full max-w-md items-stretch">
                <a
                    href="{{ route('plan.builder') }}"
                    class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 {{ request()->routeIs('plan.builder') ? 'text-primary' : 'text-base-content/60' }}"
                >
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <span class="text-[11px] font-medium">Plan</span>
                </a>

                @if ($latestLockedPlanId !== null)
                    <a
                        href="{{ route('plan.shopping-list', $latestLockedPlanId) }}"
                        class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 {{ request()->routeIs('plan.shopping-list') ? 'text-primary' : 'text-base-content/60' }}"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <span class="text-[11px] font-medium">Shop</span>
                    </a>
                @endif

                <a
                    href="{{ route('recipes.approve') }}"
                    class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 {{ request()->routeIs('recipes.approve') ? 'text-primary' : 'text-base-content/60' }}"
                >
                    <span class="{{ $pendingRecipeCount > 0 ? 'indicator' : '' }}">
                        @if ($pendingRecipeCount > 0)
                            <span class="indicator-item badge badge-primary badge-xs px-1.5 font-semibold">{{ $pendingRecipeCount }}</span>
                        @endif
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    <span class="text-[11px] font-medium">Approve</span>
                </a>

                <a
                    href="{{ route('log.catch-up') }}"
                    class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 {{ request()->routeIs('log.*') ? 'text-primary' : 'text-base-content/60' }}"
                >
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    <span class="text-[11px] font-medium">Log</span>
                </a>

                <div class="dropdown dropdown-top dropdown-end flex-1">
                    <button
                        type="button"
                        tabindex="0"
                        class="flex min-h-14 w-full flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 {{ request()->routeIs('recipes.index', 'recipes.show', 'recipes.create', 'insights', 'settings.*') ? 'text-primary' : 'text-base-content/60' }}"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <span class="text-[11px] font-medium">More</span>
                    </button>
                    <div tabindex="0" class="dropdown-content z-50 mb-2 me-2 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                        <ul class="menu w-full p-0">
                            <li>
                                <a href="{{ route('recipes.index') }}" class="min-h-11 {{ request()->routeIs('recipes.index', 'recipes.show', 'recipes.create') ? 'text-primary' : '' }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    Recipes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('insights') }}" class="min-h-11 {{ request()->routeIs('insights') ? 'text-primary' : '' }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    Insights
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings.channels') }}" class="min-h-11 {{ request()->routeIs('settings.*') ? 'text-primary' : '' }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 20.25h12m-7.5-3v3m3-3v3m-10.125-3h17.25c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                    Channels
                                </a>
                            </li>
                        </ul>
                        <div class="divider my-1"></div>
                        <div class="px-2 pb-1">
                            <div class="mb-1.5 text-xs font-medium text-base-content/60">Theme</div>
                            <div class="join w-full">
                                <button type="button" class="btn join-item btn-sm min-h-11 flex-1" :class="theme === 'light' && 'btn-primary'" @click="setTheme('light')">Light</button>
                                <button type="button" class="btn join-item btn-sm min-h-11 flex-1" :class="theme === 'dark' && 'btn-primary'" @click="setTheme('dark')">Dark</button>
                                <button type="button" class="btn join-item btn-sm min-h-11 flex-1" :class="theme === 'system' && 'btn-primary'" @click="setTheme('system')">Auto</button>
                            </div>
                        </div>
                        <div class="divider my-1"></div>
                        <form method="POST" action="{{ route('logout') }}" class="px-2 pb-1">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm min-h-11 w-full justify-start gap-3 px-2 font-normal">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                </svg>
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    @livewireScripts
</body>
</html>
