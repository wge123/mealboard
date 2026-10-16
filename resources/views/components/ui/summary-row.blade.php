{{-- Kit: summary row. A setting that reads as its answer ("Weekday time limit · 30 min ›") and opens in place to
     show a help line and the slot (the controls). Open state lives in Alpine, so a Livewire re-render leaves it
     alone; `open` only sets the starting state (e.g. when the panel holds a validation error). Stack rows in a
     flush card: they divide themselves. --}}
@props(['label', 'value', 'suffix' => null, 'help' => null, 'id' => null, 'open' => false])

@php
    $id ??= \Illuminate\Support\Str::slug($label);
@endphp

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" class="border-b border-base-300 last:border-b-0">
    <button type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $id }}-panel" :aria-expanded="open"
            x-on:click="open = ! open"
            class="flex min-h-12 w-full items-center gap-3 px-4 text-left hover:bg-base-200">
        <span class="flex-1 font-medium">{{ $label }}</span>
        <span class="font-semibold tabular-nums">{{ $value }}@if ($suffix) <span class="font-normal text-base-content/70">{{ $suffix }}</span>@endif</span>
        <x-tabler-chevron-right class="size-4 shrink-0 text-base-content/70 transition" x-bind:class="open && 'rotate-90'" />
    </button>
    <div id="{{ $id }}-panel" x-show="open" @unless ($open) x-cloak @endunless class="border-t border-base-300 bg-base-200 px-4 py-3">
        @if ($help)<p class="mb-2 text-base-content/70">{{ $help }}</p>@endif
        {{ $slot }}
    </div>
</div>
