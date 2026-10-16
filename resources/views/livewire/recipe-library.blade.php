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
        {{-- The table groups its rows the way the shelves are grouped; with a filter on, a phone shows a wall and has nothing to group. --}}
        @if (! $filtering || $view === 'table')
            <div @class(['flex items-center gap-2', 'max-lg:hidden' => $filtering])>
                @if ($view === 'table')
                    <span class="text-base-content/70 max-lg:hidden">Group by</span>
                    <span class="text-base-content/70 lg:hidden">Shelves by</span>
                @else
                    <span class="text-base-content/70">Shelves by</span>
                @endif
                <x-ui.segmented :label="$view === 'table' ? 'Group by' : 'Shelves by'">
                    @foreach (\App\Enums\LibraryShelving::cases() as $case)
                        <x-ui.segmented.option :pressed="$groupBy === $case" wire:click="$set('groupBy', '{{ $case->value }}')">{{ $case->label() }}</x-ui.segmented.option>
                    @endforeach
                </x-ui.segmented>
                <span class="mx-1 hidden h-6 w-px bg-base-300 sm:block" aria-hidden="true"></span>
            </div>
        @endif

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

        {{-- The view switch: computer only, phones stay on the shelves. --}}
        <x-ui.segmented label="View" class="ms-auto max-lg:hidden">
            <x-ui.segmented.option :pressed="$view === 'shelves'" aria-label="Shelves" title="Shelves" wire:click="$set('view', 'shelves')"><x-tabler-layout-rows /></x-ui.segmented.option>
            <x-ui.segmented.option :pressed="$view === 'table'" aria-label="Table" title="Table" wire:click="$set('view', 'table')"><x-tabler-table /></x-ui.segmented.option>
        </x-ui.segmented>
    </div>

    <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy"
         wire:target="search,toggle,groupBy,maxMinutes,minRating,clearFilters,sortBy">
        @if ($recipes->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No recipes match" icon="tabler-search">Try clearing a filter or searching for something else.</x-ui.empty-state>
            </x-ui.card>
        @elseif ($view === 'table')
            <x-ui.card flush class="hidden lg:block">
                <x-ui.table data-test="library-table">
                    <x-slot:head>
                        <tr>
                            @foreach (['title' => 'Recipe', 'meal' => 'Meal', 'cuisine' => 'Cuisine', 'time' => 'Time', 'rating' => 'Rating', 'ingredients' => 'Ingredients', 'tools' => 'Kitchen tools'] as $column => $label)
                                <x-ui.table.th :sort="$sort === $column ? $dir : null" sortable :numeric="in_array($column, ['time', 'rating', 'ingredients'], true)"
                                               wire:click="sortBy('{{ $column }}')">{{ $label }}</x-ui.table.th>
                            @endforeach
                        </tr>
                    </x-slot:head>

                    @foreach ($groups as $group)
                        <tbody wire:key="table-{{ $groupBy->value }}-{{ $group['key'] }}">
                            <x-ui.table.group :label="$group['title']" :total="$group['recipes']->count()" :cols="7" />
                            @foreach ($group['recipes'] as $recipe)
                                @php
                                    $minutes = \App\Livewire\RecipeLibrary::minutes($recipe);
                                    $rating = $ratings->get($recipe->id);
                                    $missing = collect($missingTools[$recipe->id]);
                                @endphp
                                <x-ui.table.row :group="$group['title']" wire:key="row-{{ $recipe->id }}">
                                    <td>
                                        <a href="{{ route('recipes.show', $recipe) }}" class="flex items-center gap-3 font-medium hover:text-primary">
                                            <span class="relative size-8 shrink-0 overflow-hidden rounded-field" style="{{ \App\Support\RecipeCover::background($recipe) }}" aria-hidden="true">
                                                @if ($recipe->image_url)
                                                    <img src="{{ $recipe->image_url }}" class="absolute inset-0 size-full object-cover" alt="" loading="lazy">
                                                @else
                                                    <span class="absolute inset-0 grid place-items-center text-[10px] font-semibold text-black/40">{{ \App\Support\RecipeCover::initials($recipe) }}</span>
                                                @endif
                                            </span>
                                            <span class="line-clamp-1">{{ $recipe->title }}</span>
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="size-2 rounded-full" style="background: var(--meal-{{ $recipe->meal_type->value }})" aria-hidden="true"></span>{{ ucfirst($recipe->meal_type->value) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap {{ $recipe->cuisine ? '' : 'text-base-content/70' }}">{{ $recipe->cuisine ? Str::ucfirst($recipe->cuisine) : '—' }}</td>
                                    <td class="num whitespace-nowrap {{ $minutes ? '' : 'text-base-content/70' }}">{{ $minutes ? $minutes.' min' : '—' }}</td>
                                    <td class="num whitespace-nowrap">
                                        @if ($rating !== null)
                                            <span class="inline-flex items-center gap-1"><x-tabler-star-filled class="size-3.5 text-base-content/70" />{{ number_format($rating, 1) }}</span>
                                        @else
                                            <span class="text-base-content/70">—</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $recipe->ingredients_count }}</td>
                                    <td class="whitespace-nowrap">
                                        @if ($missing->isNotEmpty())
                                            <x-ui.badge tone="warning" title="You don't have: {{ $missing->map->label()->implode(', ') }}">
                                                Needs {{ $missing->first()->label() }}{{ $missing->count() > 1 ? ' +'.($missing->count() - 1) : '' }}
                                            </x-ui.badge>
                                        @else
                                            <span class="text-base-content/70">—</span>
                                        @endif
                                    </td>
                                </x-ui.table.row>
                            @endforeach
                        </tbody>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
            {{-- The table is desktop-only: phones keep the shelves (or the wall while filtering). --}}
            <div class="lg:hidden">@include('livewire.partials.recipe-library-tiles')</div>
        @else
            @include('livewire.partials.recipe-library-tiles')
        @endif
    </div>

    {{-- FAB: phones only, floats above the bottom tab bar. --}}
    <a href="{{ route('recipes.create') }}"
       class="btn btn-circle btn-primary fixed right-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 size-14 shadow-lg lg:hidden"
       aria-label="Add recipe">
        <x-tabler-plus class="size-7" />
    </a>
</div>
