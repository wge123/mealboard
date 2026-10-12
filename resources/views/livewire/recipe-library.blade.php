<div>
    {{-- Header: title, search, and Add recipe (header button on lg+, FAB on phones). --}}
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Recipes</h1>
        @if ($filtering)
            <span class="text-sm text-base-content/70">{{ $recipes->count() }} {{ Str::plural('match', $recipes->count()) }}</span>
        @endif
        <label class="input ms-auto min-h-11 w-full sm:w-72">
            <svg class="size-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input type="search" placeholder="Search titles, descriptions, tags…"
                   wire:model.live.debounce.300ms="search" aria-label="Search recipes">
        </label>
        <a class="btn btn-primary btn-sm hidden min-h-11 lg:inline-flex" href="{{ route('recipes.create') }}">Add recipe</a>
    </div>

    {{-- Chips: any one on turns the shelves into a wall. Rows scroll sideways on phones. --}}
    @php
        $chip = 'btn btn-sm min-h-11 shrink-0 rounded-full border-base-300 aria-pressed:border-primary aria-pressed:bg-primary/10';
        $smallChip = 'btn btn-xs min-h-9 shrink-0 rounded-full border-base-300 font-normal aria-pressed:border-primary aria-pressed:bg-primary/10 aria-pressed:font-medium';
    @endphp
    <div class="mb-5 flex flex-col gap-2">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Meal and time">
            @foreach ($mealTypeCounts as $type => $count)
                <button type="button" class="{{ $chip }}" style="--slot-accent: var(--meal-{{ $type }})"
                        aria-pressed="{{ in_array($type, $mealTypes, true) ? 'true' : 'false' }}"
                        aria-label="{{ ucfirst($type) }}, {{ $count }} {{ Str::plural('recipe', $count) }}"
                        wire:click="toggle('mealTypes', '{{ $type }}')">
                    <span class="size-2 rounded-full bg-[var(--slot-accent)]" aria-hidden="true"></span>
                    {{ ucfirst($type) }} <span class="font-normal text-base-content/60">{{ $count }}</span>
                </button>
            @endforeach
            <span class="mx-1 w-px shrink-0 bg-base-300" aria-hidden="true"></span>
            @foreach ([20, 40, 60] as $limit)
                <button type="button" class="{{ $chip }}" aria-pressed="{{ $maxMinutes === $limit ? 'true' : 'false' }}"
                        wire:click="$set('maxMinutes', {{ $maxMinutes === $limit ? 'null' : $limit }})">≤ {{ $limit }} min</button>
            @endforeach
            <button type="button" class="{{ $chip }}" aria-pressed="{{ $minRating === 4 ? 'true' : 'false' }}"
                    wire:click="$set('minRating', {{ $minRating === 4 ? 'null' : 4 }})"><span class="text-warning" aria-hidden="true">★</span> 4 and up</button>
        </div>
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Cuisine and tags">
            @foreach ($cuisineOptions as $option)
                <button type="button" class="{{ $smallChip }}" aria-pressed="{{ in_array($option, $cuisines, true) ? 'true' : 'false' }}"
                        wire:click="toggle('cuisines', @js($option))">{{ Str::ucfirst($option) }}</button>
            @endforeach
            @if ($tagOptions->isNotEmpty())
                <span class="mx-1 w-px shrink-0 bg-base-300" aria-hidden="true"></span>
            @endif
            @foreach ($tagOptions as $option)
                <button type="button" class="{{ $smallChip }}" aria-pressed="{{ in_array($option, $tags, true) ? 'true' : 'false' }}"
                        wire:click="toggle('tags', @js($option))">#{{ $option }}</button>
            @endforeach
        </div>
        @if ($filtering)
            <button type="button" class="link self-start text-sm" wire:click="clearFilters">Clear filters</button>
        @endif
    </div>

    @if ($recipes->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No recipes match</p>
            <p class="mt-1 text-sm text-base-content/70">Try clearing a filter or searching for something else.</p>
        </div>
    @elseif ($filtering)
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6" data-test="wall">
            @foreach ($recipes as $recipe)
                <li wire:key="wall-{{ $recipe->id }}">
                    <x-recipe-tile :recipe="$recipe" :rating="$ratings->get($recipe->id)" :missing="$missingTools[$recipe->id]" />
                </li>
            @endforeach
        </ul>
    @else
        <div class="mb-5 flex items-center gap-2">
            <span class="text-sm text-base-content/70">Shelves by</span>
            <div class="join" role="group" aria-label="Shelves by">
                @foreach (\App\Enums\LibraryShelving::cases() as $case)
                    <button type="button" class="btn join-item btn-sm min-h-11 {{ $groupBy === $case ? 'btn-neutral' : '' }}"
                            aria-pressed="{{ $groupBy === $case ? 'true' : 'false' }}"
                            wire:click="$set('groupBy', '{{ $case->value }}')">{{ $case->label() }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-7">
            @foreach ($shelves as $shelf)
                <section data-test="shelf-{{ $shelf['key'] }}" wire:key="shelf-{{ $groupBy->value }}-{{ $shelf['key'] }}" aria-labelledby="shelf-{{ $shelf['key'] }}">
                    <h2 id="shelf-{{ $shelf['key'] }}" class="mb-2 flex items-baseline gap-2 text-lg font-semibold">
                        {{ $shelf['title'] }} <span class="text-sm font-normal text-base-content/60">{{ $shelf['recipes']->count() }}</span>
                    </h2>
                    {{-- A shelf swipes sideways, snapping to whole tiles. --}}
                    <ul class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2">
                        @foreach ($shelf['recipes'] as $recipe)
                            <li class="w-40 shrink-0 snap-start sm:w-48" wire:key="shelf-{{ $shelf['key'] }}-{{ $recipe->id }}">
                                <x-recipe-tile :recipe="$recipe" :rating="$ratings->get($recipe->id)" :missing="$missingTools[$recipe->id]" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif

    {{-- FAB: phones only, floats above the bottom tab bar. --}}
    <a href="{{ route('recipes.create') }}"
       class="btn btn-circle btn-primary fixed right-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 size-14 shadow-lg lg:hidden"
       aria-label="Add recipe">
        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    </a>
</div>
