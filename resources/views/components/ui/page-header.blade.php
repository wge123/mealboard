{{-- Kit: page header. The h1 is the only Fraunces in the kit. `back` is a URL. --}}
@props(['title', 'pretitle' => null, 'subtitle' => null, 'back' => null, 'actions' => null])

<header {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-x-4 gap-y-3']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="-ms-1 mb-1 inline-flex min-h-11 items-center gap-1 rounded-field px-1 text-base-content/70 hover:text-base-content sm:min-h-9">
                <x-tabler-arrow-left /> Back
            </a>
        @endif
        @if ($pretitle)
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/70">{{ $pretitle }}</p>
        @endif
        <h1 class="font-display text-2xl font-semibold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-base-content/70">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</header>
