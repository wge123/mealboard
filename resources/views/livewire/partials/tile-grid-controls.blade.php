{{--
    Heading row of a settings tile grid: the title, the filter and the Select
    toggle. Expects: $title, $filters (enum cases with label()), $current (the
    selected case). The wrapper must carry x-data="tileSelect".
--}}
<div class="mb-1 flex flex-wrap items-end justify-between gap-2">
    <h2 class="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight">{{ $title }}</h2>
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
</div>
