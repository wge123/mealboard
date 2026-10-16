{{--
    A recipe as a square cover tile, on the library's shelves and wall. The
    cover is the recipe's image, or a generated one (RecipeCover).
--}}
@props(['recipe', 'rating' => null, 'missing' => []])
@php
    $minutes = \App\Livewire\RecipeLibrary::minutes($recipe);
    $initials = \App\Support\RecipeCover::initials($recipe);
    $needs = collect($missing)->map->label()->implode(', ');
@endphp
<a href="{{ route('recipes.show', $recipe) }}" {{ $attributes->class('group relative block aspect-square overflow-hidden rounded-box border border-base-300') }}
   style="{{ \App\Support\RecipeCover::background($recipe) }}">
    @if ($recipe->image_url)
        <img src="{{ $recipe->image_url }}" class="absolute inset-0 size-full object-cover" alt="" loading="lazy">
    @else
        <span aria-hidden="true" class="absolute inset-0 grid place-items-center pb-8 font-display text-2xl font-semibold text-black/15 transition-transform group-hover:scale-110">{{ $initials }}</span>
    @endif

    <span class="absolute top-2 left-2 flex gap-1">
        @if ($minutes > 0)
            <x-ui.badge class="bg-base-100/90">{{ $minutes }} min</x-ui.badge>
        @endif
        @if ($rating !== null)
            <x-ui.badge class="bg-base-100/90" title="Average rating {{ number_format($rating, 1) }}"><x-tabler-star-filled class="size-3.5 text-primary" /> {{ number_format($rating, 1) }}</x-ui.badge>
        @endif
    </span>

    @if ($needs !== '')
        <span class="absolute top-2 right-2 rounded-full bg-base-100"><x-ui.badge tone="warning" data-missing-badge="true" title="needs: {{ $needs }}"><x-tabler-tool class="size-3" /><span class="sr-only">needs: {{ $needs }}</span></x-ui.badge></span>
    @endif

    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/40 to-transparent p-3 pt-10">
        <span class="line-clamp-2 text-sm leading-tight font-semibold text-white">{{ $recipe->title }}</span>
        <span class="block truncate text-xs text-white/80">{{ collect([ucfirst($recipe->meal_type->value), $recipe->cuisine ? \Illuminate\Support\Str::ucfirst($recipe->cuisine) : null])->filter()->implode(' · ') }}</span>
    </span>
</a>
