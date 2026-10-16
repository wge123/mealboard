{{-- The wall of tiles while filtering, the swipeable shelves otherwise. The shelves view uses it, and so does the phone when the table is chosen. --}}
@if ($filtering)
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
