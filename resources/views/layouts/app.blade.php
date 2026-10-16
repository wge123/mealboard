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
    class="min-h-screen bg-base-200 text-base-content"
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
    @php
        // Screens pick the content width: standard (today's cap) or wide (the full window with a gutter).
        // A Livewire screen passes #[Layout('layouts.app', ['width' => 'wide'])]; a plain view uses @section('width', 'wide').
        $wide = ($width ?? trim($__env->yieldContent('width'))) === 'wide';

        $item = 'inline-flex min-h-11 items-center gap-2 rounded-field px-3 font-medium sm:min-h-9';
        $onShop = request()->routeIs('shop', 'plan.shopping-list');
        $onRecipes = request()->routeIs('recipes.index', 'recipes.show', 'recipes.create', 'recipes.request');
        $themeButtons = ['light' => 'Light', 'dark' => 'Dark', 'system' => 'Auto'];
    @endphp

    {{-- Desktop: a slim sticky top bar (lg+ only; phones get the bottom tabs and no top bar). --}}
    <header class="sticky top-0 z-30 hidden border-b border-base-300 bg-base-100 lg:block">
        <nav aria-label="Main" class="mx-auto flex h-14 w-full items-center gap-1 px-6 {{ $wide ? 'lg:px-8' : 'max-w-6xl' }}">
            <a href="{{ url('/') }}" class="me-4 text-base font-semibold text-primary">Mealboard</a>
            @auth
                <div class="flex items-center gap-1">
                    <a href="{{ route('plan.builder') }}" class="{{ $item }} {{ request()->routeIs('plan.builder') ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if (request()->routeIs('plan.builder')) aria-current="page" @endif>
                        <x-tabler-calendar-week /> Plan
                    </a>
                    <a href="{{ route('shop') }}" class="{{ $item }} {{ $onShop ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if ($onShop) aria-current="page" @endif>
                        <x-tabler-shopping-cart /> Shop
                    </a>
                    <a href="{{ route('recipes.index') }}" class="{{ $item }} {{ $onRecipes ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if ($onRecipes) aria-current="page" @endif>
                        <x-tabler-book-2 /> Recipes
                    </a>
                    <a href="{{ route('recipes.approve') }}" class="{{ $item }} {{ request()->routeIs('recipes.approve') ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if (request()->routeIs('recipes.approve')) aria-current="page" @endif>
                        <x-tabler-circle-check /> Approve
                        @if ($pendingRecipeCount > 0)
                            <x-ui.badge tone="primary">{{ $pendingRecipeCount }}</x-ui.badge>
                        @endif
                    </a>
                    <a href="{{ route('log.catch-up') }}" class="{{ $item }} {{ request()->routeIs('log.*') ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if (request()->routeIs('log.*')) aria-current="page" @endif>
                        <x-tabler-edit /> Log
                    </a>
                    <a href="{{ route('insights') }}" class="{{ $item }} {{ request()->routeIs('insights') ? 'bg-base-200 text-primary' : 'text-base-content/70 hover:text-base-content' }}" @if (request()->routeIs('insights')) aria-current="page" @endif>
                        <x-tabler-chart-bar /> Insights
                    </a>
                </div>
            @endauth
            <div class="ms-auto flex items-center">
                @auth
                    <div class="dropdown dropdown-end">
                        <button type="button" tabindex="0" class="{{ $item }} text-base-content/70 hover:text-base-content" aria-haspopup="menu">
                            <x-tabler-user-circle />
                            {{ auth()->user()->name }}
                            <x-tabler-chevron-down class="size-4" />
                        </button>
                        <div tabindex="0" class="dropdown-content z-40 mt-1 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                            <ul class="menu w-full p-0">
                                <li>
                                    <a href="{{ route('settings') }}" class="min-h-11 sm:min-h-9 {{ request()->routeIs('settings') ? 'text-primary' : '' }}">
                                        <x-tabler-settings /> Settings
                                    </a>
                                </li>
                            </ul>
                            <div class="divider my-1"></div>
                            <div class="px-2 pb-1">
                                <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-base-content/70">Theme</div>
                                <div class="join w-full">
                                    @foreach ($themeButtons as $key => $label)
                                        <button type="button" class="btn join-item btn-sm min-h-11 flex-1 sm:min-h-9" :class="theme === '{{ $key }}' && 'btn-primary'" :aria-pressed="theme === '{{ $key }}'" @click="setTheme('{{ $key }}')">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="divider my-1"></div>
                            <form method="POST" action="{{ route('logout') }}" class="px-2 pb-1">
                                @csrf
                                <x-ui.button type="submit" variant="ghost" icon="tabler-logout" class="w-full justify-start">Log out</x-ui.button>
                            </form>
                        </div>
                    </div>
                @else
                    <x-ui.button variant="primary" href="{{ route('login') }}">Log in</x-ui.button>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full px-4 py-6 pb-24 sm:px-6 lg:pb-12 {{ $wide ? 'lg:px-8' : 'max-w-6xl' }}">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- Phone: five bottom tabs (thumb-reach nav; hidden on lg+). --}}
    @auth
        @php
            $tab = 'relative flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1';
            $onMore = request()->routeIs('recipes.index', 'recipes.show', 'recipes.create', 'recipes.request', 'insights', 'settings');
        @endphp
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-base-300 bg-base-100 lg:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
            aria-label="Primary"
        >
            <div class="mx-auto flex w-full max-w-md items-stretch">
                <a href="{{ route('plan.builder') }}" class="{{ $tab }} {{ request()->routeIs('plan.builder') ? 'text-primary' : 'text-base-content/70' }}" @if (request()->routeIs('plan.builder')) aria-current="page" @endif>
                    <x-tabler-calendar-week class="size-6" />
                    <span class="text-xs font-medium">Plan</span>
                </a>

                <a href="{{ route('shop') }}" class="{{ $tab }} {{ $onShop ? 'text-primary' : 'text-base-content/70' }}" @if ($onShop) aria-current="page" @endif>
                    <x-tabler-shopping-cart class="size-6" />
                    <span class="text-xs font-medium">Shop</span>
                </a>

                <a href="{{ route('recipes.approve') }}" class="{{ $tab }} {{ request()->routeIs('recipes.approve') ? 'text-primary' : 'text-base-content/70' }}" @if (request()->routeIs('recipes.approve')) aria-current="page" @endif>
                    <x-tabler-circle-check class="size-6" />
                    <span class="text-xs font-medium">Approve</span>
                    @if ($pendingRecipeCount > 0)
                        <x-ui.badge tone="primary" class="absolute start-1/2 top-1 ms-2 px-1.5">{{ $pendingRecipeCount }}</x-ui.badge>
                    @endif
                </a>

                <a href="{{ route('log.catch-up') }}" class="{{ $tab }} {{ request()->routeIs('log.*') ? 'text-primary' : 'text-base-content/70' }}" @if (request()->routeIs('log.*')) aria-current="page" @endif>
                    <x-tabler-edit class="size-6" />
                    <span class="text-xs font-medium">Log</span>
                </a>

                <div class="dropdown dropdown-top dropdown-end flex-1">
                    <button type="button" tabindex="0" aria-haspopup="menu" class="{{ $tab }} w-full {{ $onMore ? 'text-primary' : 'text-base-content/70' }}">
                        <x-tabler-dots class="size-6" />
                        <span class="text-xs font-medium">More</span>
                    </button>
                    <div tabindex="0" class="dropdown-content z-50 mb-2 me-2 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                        <ul class="menu w-full p-0">
                            <li>
                                <a href="{{ route('recipes.index') }}" class="min-h-11 {{ request()->routeIs('recipes.index', 'recipes.show', 'recipes.create', 'recipes.request') ? 'text-primary' : '' }}">
                                    <x-tabler-book-2 class="size-5" />
                                    Recipes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('insights') }}" class="min-h-11 {{ request()->routeIs('insights') ? 'text-primary' : '' }}">
                                    <x-tabler-chart-bar class="size-5" />
                                    Insights
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings') }}" class="min-h-11 {{ request()->routeIs('settings') ? 'text-primary' : '' }}">
                                    <x-tabler-settings class="size-5" />
                                    Settings
                                </a>
                            </li>
                        </ul>
                        <div class="divider my-1"></div>
                        <div class="px-2 pb-1">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-base-content/70">Theme</div>
                            <div class="join w-full">
                                @foreach ($themeButtons as $key => $label)
                                    <button type="button" class="btn join-item btn-sm min-h-11 flex-1" :class="theme === '{{ $key }}' && 'btn-primary'" :aria-pressed="theme === '{{ $key }}'" @click="setTheme('{{ $key }}')">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="divider my-1"></div>
                        <form method="POST" action="{{ route('logout') }}" class="px-2 pb-1">
                            @csrf
                            <x-ui.button type="submit" variant="ghost" icon="tabler-logout" class="w-full justify-start">Log out</x-ui.button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    @livewireScripts
</body>
</html>
