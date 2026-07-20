<div>
    {{-- Header: title + Add recipe (header button on lg+, FAB on phones). --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Recipes</h1>
        <a class="btn btn-primary btn-sm hidden min-h-11 lg:inline-flex" href="{{ route('recipes.create') }}">Add recipe</a>
    </div>

    {{-- Filter bar: search always visible; selects collapse behind "Filters" on phones. --}}
    <div class="mb-6" x-data="{ filtersOpen: false }">
        <div class="flex gap-2">
            <label class="input min-h-11 w-full lg:max-w-sm">
                <svg class="size-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" placeholder="Search title or description…"
                       wire:model.live.debounce.300ms="search" aria-label="Search recipes">
            </label>
            <button type="button" class="btn min-h-11 shrink-0 lg:hidden"
                    @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="recipe-filters">
                Filters
            </button>
        </div>

        <div id="recipe-filters"
             class="mt-2 grid-cols-2 gap-2 lg:mt-3 lg:flex lg:flex-wrap"
             :class="filtersOpen ? 'grid' : 'hidden'">
            <select class="select min-h-11 w-full lg:w-auto" wire:model.live="mealType" aria-label="Meal type">
                <option value="">Any meal</option>
                @foreach ($mealTypes as $type)
                    <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                @endforeach
            </select>
            <select class="select min-h-11 w-full lg:w-auto" wire:model.live="cuisine" aria-label="Cuisine">
                <option value="">Any cuisine</option>
                @foreach ($cuisines as $option)
                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <select class="select min-h-11 w-full lg:w-auto" wire:model.live="tag" aria-label="Tag">
                <option value="">Any tag</option>
                @foreach ($tags as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <select class="select min-h-11 w-full lg:w-auto" wire:model.live="minRating" aria-label="Minimum rating">
                <option value="">Any rating</option>
                @foreach ([5, 4, 3, 2] as $stars)
                    <option value="{{ $stars }}">{{ $stars }}★ &amp; up</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($recipes->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No recipes match</p>
            <p class="mt-1 text-sm opacity-60">Try clearing a filter or searching for something else.</p>
        </div>
    @else
        {{-- Card grid: 1 col on phones, 2 from sm, 3 from lg. Whole card links to the recipe. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($recipes as $recipe)
                @php($minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes)
                @php($avg = $ratings->get($recipe->id))
                @php($emoji = ['breakfast' => '🍳', 'lunch' => '🥪', 'dinner' => '🍽️', 'any' => '🍲'][$recipe->meal_type->value])
                <a href="{{ route('recipes.show', $recipe) }}"
                   class="group overflow-hidden rounded-2xl border border-base-300 bg-base-100 transition-colors hover:border-primary/50">
                    {{-- Media strip: recipe image, or a meal-type gradient placeholder. --}}
                    <figure class="aspect-[3/1]">
                        @if ($recipe->image_url)
                            <img src="{{ $recipe->image_url }}" class="h-full w-full object-cover" alt="" loading="lazy">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-base-200"
                                 style="background-image: linear-gradient(135deg, color-mix(in oklab, var(--meal-{{ $recipe->meal_type->value }}) 45%, transparent), transparent)">
                                <span class="text-4xl" aria-hidden="true">{{ $emoji }}</span>
                            </div>
                        @endif
                    </figure>

                    <div class="p-4">
                        <h2 class="font-[family-name:var(--font-display)] text-lg leading-snug font-semibold group-hover:text-primary">
                            {{ $recipe->title }}
                        </h2>

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="badge badge-ghost badge-sm slot-label font-medium"
                                  style="--slot-accent: var(--meal-{{ $recipe->meal_type->value }})">
                                {{ ucfirst($recipe->meal_type->value) }}
                            </span>
                            @if ($recipe->cuisine)
                                <span class="badge badge-ghost badge-sm">{{ ucfirst($recipe->cuisine) }}</span>
                            @endif
                            @if ($minutes > 0)
                                <span class="badge badge-ghost badge-sm">{{ $minutes }} min</span>
                            @endif
                            @if ($avg !== null)
                                @php($stars = (int) round((float) $avg))
                                <span class="badge badge-ghost badge-sm gap-1" title="Average rating {{ number_format((float) $avg, 1) }}">
                                    <span class="text-warning" aria-hidden="true">{{ str_repeat('★', $stars) }}{{ str_repeat('☆', 5 - $stars) }}</span>
                                    <span class="opacity-70">{{ number_format((float) $avg, 1) }}</span>
                                </span>
                            @endif
                        </div>

                        @if (($recipe->tags ?? []) !== [])
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($recipe->tags as $recipeTag)
                                    <span class="badge badge-ghost badge-xs">{{ $recipeTag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </a>
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
