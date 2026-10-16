{{-- Kit: filter menu, a toolbar button that opens a list of options (<x-ui.filter-menu.option>). `count` is how many values are on
     (a multi-pick menu); `value` is the chosen value of a one-of-N menu (`single`, which closes after a pick) and replaces the label.
     Either tints the button and is announced in its name. `busy` (a wire:target) disables the button while that action runs. --}}
@props(['label', 'count' => 0, 'value' => null, 'single' => false, 'busy' => null])

@php
    $active = $value !== null || $count > 0;
    $name = $value !== null ? "{$label}, {$value}" : ($count > 0 ? "{$label}, {$count} on" : null);
    $state = $active ? 'border-primary bg-primary/10 text-primary' : 'border-base-300 bg-base-100 hover:bg-base-200';
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
    <button
        type="button"
        aria-haspopup="true"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        @if ($name) aria-label="{{ $name }}" @endif
        @click="open = ! open"
        {{ (new \Illuminate\View\ComponentAttributeBag(\App\Support\Kit::busy($busy)))->merge(['class' => "hit-area inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-field border px-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60 {$state}"]) }}
    >
        {{ $value ?? $label }}@if (! $single && $count > 0)<span class="tabular-nums">· {{ $count }}</span>@endif
        <x-tabler-chevron-down class="size-3.5" />
    </button>
    <div
        x-cloak x-show="open" x-transition.opacity.duration.100ms
        role="group" aria-label="{{ $label }}"
        @if ($single) @click="open = false" @endif
        class="absolute start-0 z-30 mt-1 flex max-sm:fixed max-sm:inset-x-4 max-h-72 min-w-48 flex-col overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
    >{{ $slot }}</div>
</div>
