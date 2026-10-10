{{--
    Shared frame for every settings page: a section menu above the page content.
    To add a settings section, add ONE line to $sections (label => route name).
    Section routes must be named settings.*, which the top nav and tab bar use to
    highlight the settings area.
--}}
@php
    $sections = [
        'Kitchen tools' => 'settings.kitchen-tools',
        'Channels' => 'settings.channels',
    ];
@endphp

<div class="mx-auto w-full max-w-2xl">
    <nav aria-label="Settings sections" class="mb-5">
        <div role="tablist" class="tabs tabs-border overflow-x-auto">
            @foreach ($sections as $label => $routeName)
                <a href="{{ route($routeName) }}" role="tab"
                   class="tab min-h-11 {{ request()->routeIs($routeName) ? 'tab-active' : '' }}"
                   @if (request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    {{ $slot }}
</div>
