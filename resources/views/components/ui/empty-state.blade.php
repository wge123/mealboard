{{-- Kit: empty state. Centred, no box of its own: it sits inside a card. --}}
@props(['title', 'icon' => null, 'action' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 px-4 py-10 text-center']) }}>
    @if ($icon)
        <span class="text-base-content/70"><x-dynamic-component :component="$icon" class="size-8" /></span>
    @endif
    <p class="font-semibold">{{ $title }}</p>
    @if (trim((string) $slot) !== '')
        <p class="max-w-prose text-base-content/70">{{ $slot }}</p>
    @endif
    @if ($action)
        <div class="mt-2 flex flex-wrap items-center justify-center gap-2">{{ $action }}</div>
    @endif
</div>
