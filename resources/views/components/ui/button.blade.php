{{-- Kit: button. One size. `busy` is a Livewire wire:target (action class of the loading rule):
     it disables the button and swaps the icon (or leading spot) for a spinner at once.
     `label` is the accessible name of a `square` icon-only button and is required there. --}}
@props([
    'variant' => 'secondary',
    'href' => null,
    'icon' => null,
    'label' => null,
    'square' => false,
    'busy' => null,
    'busyLabel' => null,
])

@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'border-base-300 bg-base-100 hover:bg-base-200 aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-primary-content',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-ghost text-error hover:bg-error hover:text-error-content',
    ];

    if (! isset($variants[$variant])) {
        throw new InvalidArgumentException("Unknown button variant [{$variant}].");
    }
    if ($square && blank($label)) {
        throw new InvalidArgumentException('A square button needs a label: it is the only accessible name.');
    }

    $classes = 'btn min-h-11 sm:min-h-9 h-auto rounded-field px-3 font-medium shadow-none '.$variants[$variant].($square ? ' btn-square px-0' : '');
    $tag = $href ? 'a' : 'button';
    $spinner = '<span class="loading loading-spinner loading-xs hidden" data-spinner aria-hidden="true" wire:loading.class.remove="hidden" wire:target="'.e($busy).'"></span>';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($square) aria-label="{{ $label }}" @endif
    {{ $attributes->merge($href ? [] : ['type' => 'button'])->merge(\App\Support\Kit::busy($busy))->merge(['class' => $classes]) }}
>
    @if ($busy && ! $icon){!! $spinner !!}@endif
    @if ($icon)
        @if ($busy)
            {!! $spinner !!}
            <span class="contents" wire:loading.class="hidden" wire:target="{{ $busy }}"><x-dynamic-component :component="$icon" /></span>
        @else
            <x-dynamic-component :component="$icon" />
        @endif
    @endif
    @unless ($square)
        @if ($busy && $busyLabel)
            <span wire:loading.class="hidden" wire:target="{{ $busy }}">{{ $slot }}</span>
            <span class="hidden" wire:loading.class.remove="hidden" wire:target="{{ $busy }}">{{ $busyLabel }}</span>
        @else
            {{ $slot }}
        @endif
    @endunless
</{{ $tag }}>
