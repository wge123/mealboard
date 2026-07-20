{{-- Bare centered shell for unauthenticated/standalone pages (login, error pages).
     Deliberately independent of layouts.app: no nav, no auth, no Livewire. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', config('app.name', 'Mealboard'))</title>
    <script>
        // Apply persisted theme before first paint (system = no attribute).
        (() => {
            const t = localStorage.getItem('mealboard-theme');
            if (t === 'dark') document.documentElement.dataset.theme = 'mealboard-dark';
            else if (t === 'light') document.documentElement.dataset.theme = 'mealboard';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-base-200 p-4 text-base-content">
    @yield('content')
</body>
</html>
