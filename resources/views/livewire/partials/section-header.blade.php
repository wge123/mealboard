{{--
    Heading of a settings section: the title and one line of help, with the
    filter and the Select toggle beside them for a tile grid. Expects: $title,
    $help; for a tile grid also $filters (enum cases with label()) and $current
    (the selected case), and the wrapper must carry x-data="tileSelect".
--}}
<header class="mb-5 flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
    <div class="min-w-0 flex-1 basis-72">
        <h2 class="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight">{{ $title }}</h2>
        <p class="mt-1 max-w-prose text-sm text-base-content/70">{{ $help }}</p>
    </div>
    @isset($filters)
        <div class="flex gap-2">
            <div class="join" role="group" aria-label="Show">
                @foreach ($filters as $case)
                    <button type="button" class="btn join-item btn-sm min-h-11 {{ $current === $case ? 'btn-neutral' : '' }}"
                            wire:click="$set('filter', '{{ $case->value }}')"
                            aria-pressed="{{ $current === $case ? 'true' : 'false' }}">{{ $case->label() }}</button>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm min-h-11" :class="selecting && 'btn-primary'"
                    x-on:click="toggleSelecting()" x-text="selecting ? 'Cancel' : 'Select'">Select</button>
        </div>
    @endisset
</header>
