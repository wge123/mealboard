{{--
    The settings page. To add a settings section, add ONE entry to $sections
    (anchor => [label, phone label, component, menu count or null, what the count counts])
    and have the component dispatch Settings::CHANGED if it changes a count the menu shows.
--}}
@php
    $sections = [
        'tools' => ['Kitchen tools', 'Tools', 'kitchen-tools', $ownedCount, 'owned'],
        'preferences' => ['Preferences', 'Preferences', 'preferences', null, null],
        'pantry' => ['Pantry staples', 'Pantry', 'pantry-staples', $stapleCount, 'on hand'],
        'channels' => ['YouTube channels', 'Channels', 'channel-settings', $activeChannelCount, 'active'],
    ];
@endphp

<div class="lg:grid lg:grid-cols-[13rem_1fr] lg:gap-10">
    {{-- Section menu: sticky beside the page on desktop, a chip row on phones; it marks the section in view --}}
    <nav aria-label="Settings sections" x-data="settingsMenu"
         class="sticky top-0 z-20 -mx-4 mb-4 flex gap-1 overflow-x-auto border-b border-base-300 bg-base-100 px-4 py-2 lg:top-20 lg:mx-0 lg:mb-0 lg:h-fit lg:flex-col lg:gap-1 lg:border-0 lg:p-0">
        <h1 class="mb-3 hidden font-display text-2xl font-semibold tracking-tight lg:block">Settings</h1>
        @foreach ($sections as $anchor => [$label, $short, $component, $count, $counts])
            <a href="#{{ $anchor }}" :aria-current="current === '{{ $anchor }}' && 'location'"
               class="btn btn-ghost btn-sm min-h-11 shrink-0 justify-between gap-2 px-2.5 font-medium lg:gap-3 lg:px-3 aria-[current=location]:bg-base-200 aria-[current=location]:text-primary lg:w-full">
                <span class="lg:hidden">{{ $short }}</span>
                <span class="hidden lg:inline">{{ $label }}</span>
                @if ($count)
                    <span class="badge badge-sm" aria-label="{{ $count }} {{ $counts }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="min-w-0 space-y-12 pb-16">
        <h1 class="font-display text-2xl font-semibold tracking-tight lg:hidden">Settings</h1>
        @foreach ($sections as $anchor => [$label, $short, $component])
            <section id="{{ $anchor }}" class="scroll-mt-20">
                <livewire:dynamic-component :is="$component" :key="$anchor" />
            </section>
        @endforeach
    </div>
</div>
