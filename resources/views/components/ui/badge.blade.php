{{-- Kit: badge. Static, never clickable. --}}
@props(['tone' => 'neutral', 'dot' => false])

@php
    $tones = [
        'neutral' => ['border-base-300 text-base-content/70', 'bg-base-content/40'],
        'primary' => ['border-primary/30 bg-primary/10 text-primary', 'bg-primary'],
        'info' => ['border-info/30 bg-info/10 text-base-content', 'bg-info'],
        'warning' => ['border-warning/40 bg-warning/10 text-base-content', 'bg-warning'],
        'error' => ['border-error/30 bg-error/10 text-base-content', 'bg-error'],
    ];

    if (! isset($tones[$tone])) {
        throw new InvalidArgumentException("Unknown badge tone [{$tone}].");
    }
    [$classes, $dotClass] = $tones[$tone];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium {$classes}"]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
