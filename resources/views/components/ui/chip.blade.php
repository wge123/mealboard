{{-- Kit: chip, a pressed/unpressed toggle. `busy` is a Livewire wire:target: it disables the chip while that action runs.
     `tone="ink"` fills the pressed chip with ink and dashes the resting one (avoided words and their suggestions).
     The visual is 32px; the hit-area utility extends the hit area to 44px. A passed data-test sits right before aria-pressed: the settings tests read the pair together. --}}
@props(['pressed' => false, 'busy' => null, 'icon' => null, 'tone' => 'default'])

@php
    $state = match (true) {
        $tone === 'ink' && $pressed => 'border-base-content bg-base-content text-base-100',
        $tone === 'ink' => 'border-dashed border-base-300 bg-base-100 text-base-content/70 hover:border-base-content/40',
        $pressed => 'border-primary bg-primary/10 text-primary',
        default => 'border-base-300 bg-base-100 hover:bg-base-200',
    };
@endphp

<button
    {{ $attributes->only('data-test') }} aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->except('data-test')->merge(['type' => 'button'])->merge(\App\Support\Kit::busy($busy))->merge(['class' => "hit-area inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60 {$state}"]) }}
>
    @if ($icon)<x-dynamic-component :component="$icon" />@endif
    {{ $slot }}
</button>
