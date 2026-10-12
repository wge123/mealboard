{{--
    The settings page. To add a settings section, add ONE entry to $sections
    (anchor => [label, component]) and give the component a settings-changed
    dispatch if it changes a count the menu shows.
--}}
@php
    $sections = [
        'tools' => ['Kitchen tools', 'kitchen-tools', $ownedCount],
        'preferences' => ['Preferences', 'preferences', null],
        'pantry' => ['Pantry staples', 'pantry-staples', $stapleCount],
        'channels' => ['Channels', 'channel-settings', $activeChannelCount],
    ];
@endphp

<div class="lg:grid lg:grid-cols-[13rem_1fr] lg:gap-10">
    {{-- Section menu: sticky beside the page on desktop, a scrolling chip row on phones --}}
    <nav aria-label="Settings sections"
         class="sticky top-0 z-20 -mx-4 mb-4 flex gap-2 overflow-x-auto border-b border-base-300 bg-base-100 px-4 py-2 lg:top-6 lg:mx-0 lg:mb-0 lg:h-fit lg:flex-col lg:gap-1 lg:border-0 lg:p-0">
        <h1 class="mb-3 hidden font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight lg:block">Settings</h1>
        @foreach ($sections as $anchor => [$label, $component, $count])
            <a href="#{{ $anchor }}" class="btn btn-ghost btn-sm min-h-11 shrink-0 justify-between gap-3 font-medium lg:w-full">
                {{ $label }}
                @isset($count)
                    <span class="badge badge-sm">{{ $count }}</span>
                @endisset
            </a>
        @endforeach
    </nav>

    <div class="min-w-0 space-y-12 pb-16">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight lg:hidden">Settings</h1>
        @foreach ($sections as $anchor => [$label, $component, $count])
            <section id="{{ $anchor }}" class="scroll-mt-20 lg:scroll-mt-6">
                <livewire:dynamic-component :is="$component" :key="$anchor" />
            </section>
        @endforeach
    </div>
</div>
