{{-- Kit: one option of a segmented toggle. Reads `busy` from the group. `tone="primary"` paints the pressed option herb green.
     The look follows aria-pressed, so a caller may bind x-bind:aria-pressed (Alpine) and the option changes at once, before any server round trip. --}}
@aware(['busy' => null])
@props(['pressed' => false, 'tone' => null])

@php
    $pressedState = $tone === 'primary'
        ? 'aria-pressed:bg-primary aria-pressed:text-primary-content'
        : 'aria-pressed:bg-base-100 aria-pressed:text-base-content aria-pressed:ring-1 aria-pressed:ring-base-300';
    $state = "not-aria-pressed:text-base-content/70 not-aria-pressed:hover:text-base-content {$pressedState}";
@endphp

<button
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->merge(['type' => 'button'])->merge(\App\Support\Kit::busy($busy))->merge(['class' => "hit-area inline-flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded-field px-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60 {$state}"]) }}
>{{ $slot }}</button>
