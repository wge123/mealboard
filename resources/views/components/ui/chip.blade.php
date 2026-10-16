{{-- Kit: chip, a pressed/unpressed filter toggle. `busy` is a Livewire wire:target: it disables the chip while that action runs.
     The visual is 32px; a pseudo-element extends the hit area to 44px. --}}
@props(['pressed' => false, 'busy' => null, 'icon' => null])

@php
    $state = $pressed ? 'border-primary bg-primary/10 text-primary' : 'border-base-300 bg-base-100 hover:bg-base-200';
    $busyAttributes = $busy ? ['wire:loading.attr' => 'disabled', 'wire:target' => $busy] : [];
@endphp

<button
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->merge(['type' => 'button'])->merge($busyAttributes)->merge(['class' => "relative inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 text-sm font-medium transition before:absolute before:inset-x-0 before:-inset-y-1.5 before:content-[''] disabled:cursor-not-allowed disabled:opacity-60 {$state}"]) }}
>
    @if ($icon)<x-dynamic-component :component="$icon" />@endif
    {{ $slot }}
</button>
