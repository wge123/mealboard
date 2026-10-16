<div>
    <x-ui.page-header title="Recipes" class="mb-4"
        :subtitle="$recipes->count().($filtering ? ' ' : ' approved ').Str::plural('recipe', $recipes->count()).($filtering ? ' match' : '')">
        <x-slot:actions>
            <label class="input min-h-11 w-full sm:min-h-9 sm:w-64">
                <x-tabler-search class="size-4 text-base-content/70" />
                <input type="search" placeholder="Search recipes…"
                       wire:model.live.debounce.300ms="search" aria-label="Search recipes">
            </label>
            <x-ui.button :href="route('recipes.request')" icon="tabler-message">Request a recipe</x-ui.button>
            <x-ui.button :href="route('recipes.create')" variant="primary" icon="tabler-plus" class="hidden lg:inline-flex">Add recipe</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Toolbar: one row of filter menus. Any one on turns the shelves into a wall. --}}
    <div class="mb-5 flex flex-wrap items-center gap-2" role="toolbar" aria-label="Filter recipes">
        @unless ($filtering)
            <span class="text-base-content/70">Shelves by</span>
            <x-ui.segmented label="Shelves by">
                @foreach (\App\Enums\LibraryShelving::cases() as $case)
                    <x-ui.segmented.option :pressed="$groupBy === $case" wire:click="$set('groupBy', '{{ $case->value }}')">{{ $case->label() }}</x-ui.segmented.option>
                @endforeach
            </x-ui.segmented>
            <span class="mx-1 hidden h-6 w-px bg-base-300 sm:block" aria-hidden="true"></span>
        @endunless

        <x-ui.filter-menu label="Meal" :count="count($mealTypes)">
            @foreach ($mealTypeCounts as $type => $count)
                <x-ui.filter-menu.option :pressed="in_array($type, $mealTypes, true)" style="--slot-accent: var(--meal-{{ $type }})"
                                         aria-label="{{ ucfirst($type) }}, {{ $count }} {{ Str::plural('recipe', $count) }}"
                                         wire:click="toggle('mealTypes', '{{ $type }}')">
                    <span class="size-2 rounded-full bg-[var(--slot-accent)]" aria-hidden="true"></span>
                    {{ ucfirst($type) }}
                    <span class="ms-auto tabular-nums text-base-content/70">{{ $count }}</span>
                </x-ui.filter-menu.option>
            @endforeach
        </x-ui.filter-menu>

        @if ($cuisineOptions->isNotEmpty())
            <x-ui.filter-menu label="Cuisine" :count="count($cuisines)">
                @foreach ($cuisineOptions as $option)
                    <x-ui.filter-menu.option :pressed="in_array($option, $cuisines, true)" wire:click="toggle('cuisines', @js($option))">{{ Str::ucfirst($option) }}</x-ui.filter-menu.option>
                @endforeach
            </x-ui.filter-menu>
        @endif

        @if ($tagOptions->isNotEmpty())
            <x-ui.filter-menu label="Tags" :count="count($tags)">
                @foreach ($tagOptions as $option)
                    <x-ui.filter-menu.option :pressed="in_array($option, $tags, true)" wire:click="toggle('tags', @js($option))">#{{ $option }}</x-ui.filter-menu.option>
                @endforeach
            </x-ui.filter-menu>
        @endif

        <x-ui.filter-menu label="Time" single :value="$maxMinutes ? '≤ '.$maxMinutes.' min' : null">
            @foreach ([null, 20, 40, 60] as $limit)
                <x-ui.filter-menu.option :pressed="$maxMinutes === $limit" wire:click="$set('maxMinutes', {{ $limit ?? 'null' }})">{{ $limit ? '≤ '.$limit.' min' : 'Any time' }}</x-ui.filter-menu.option>
            @endforeach
        </x-ui.filter-menu>

        <x-ui.chip icon="tabler-star-filled" :pressed="$minRating === 4" wire:click="$set('minRating', {{ $minRating === 4 ? 'null' : 4 }})">4 and up</x-ui.chip>

        @if ($filtering)
            <x-ui.button variant="ghost" icon="tabler-x" wire:click="clearFilters">Clear filters</x-ui.button>
        @endif
    </div>

    <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy"
         wire:target="search,toggle,groupBy,maxMinutes,minRating,clearFilters">
        @if ($recipes->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No recipes match" icon="tabler-search">Try clearing a filter or searching for something else.</x-ui.empty-state>
            </x-ui.card>
        @elseif ($filtering)
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6" data-test="wall">
                @foreach ($recipes as $recipe)
                    <li wire:key="wall-{{ $recipe->id }}">
                        <x-recipe-tile :recipe="$recipe" :rating="$ratings->get($recipe->id)" :missing="$missingTools[$recipe->id]" />
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex flex-col gap-7">
                @foreach ($shelves as $shelf)
                    <section data-test="shelf-{{ $shelf['key'] }}" wire:key="shelf-{{ $groupBy->value }}-{{ $shelf['key'] }}" aria-labelledby="shelf-{{ $shelf['key'] }}">
                        <h2 id="shelf-{{ $shelf['key'] }}" class="mb-2 flex items-baseline gap-2 text-xs font-semibold uppercase tracking-wide text-base-content/70">
                            {{ $shelf['title'] }} <span class="font-normal tabular-nums">{{ $shelf['recipes']->count() }}</span>
                        </h2>
                        {{-- A shelf swipes sideways, snapping to whole tiles. --}}
                        <ul class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-8 lg:scroll-px-8 lg:px-8">
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
    </div>

    {{-- FAB: phones only, floats above the bottom tab bar. --}}
    <a href="{{ route('recipes.create') }}"
       class="btn btn-circle btn-primary fixed right-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 size-14 shadow-lg lg:hidden"
       aria-label="Add recipe">
        <x-tabler-plus class="size-7" />
    </a>
</div>
