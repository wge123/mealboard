{{-- Kit: one option of a segmented toggle. Reads `busy` from the group. `tone="primary"` paints the pressed option herb green. --}}
@aware(['busy' => null])
@props(['pressed' => false, 'tone' => null])

@php
    $pressedState = $tone === 'primary' ? 'bg-primary text-primary-content' : 'bg-base-100 text-base-content ring-1 ring-base-300';
    $state = $pressed ? $pressedState : 'text-base-content/70 hover:text-base-content';
    $busyAttributes = $busy ? ['wire:loading.attr' => 'disabled', 'wire:target' => $busy] : [];
@endphp

<button
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->merge(['type' => 'button'])->merge($busyAttributes)->merge(['class' => "relative inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-field px-3 text-sm font-medium transition before:absolute before:inset-x-0 before:-inset-y-1.5 before:content-[''] disabled:cursor-not-allowed disabled:opacity-60 {$state}"]) }}
>{{ $slot }}</button>
