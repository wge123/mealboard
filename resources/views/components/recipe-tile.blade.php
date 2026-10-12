{{--
    A recipe as a square cover tile, on the library's shelves and wall. The
    cover is the recipe's image, or a generated one: its meal colour washed
    with a hue taken from the title, so tiles tell apart without photos.
--}}
@props(['recipe', 'rating' => null, 'missing' => []])
@php
    $minutes = \App\Livewire\RecipeLibrary::minutes($recipe);
    $hue = crc32($recipe->title) % 360;
    $initials = collect(preg_split('/\s+/', $recipe->title))->filter(fn ($word) => preg_match('/^\p{L}/u', $word))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $needs = collect($missing)->map->label()->implode(', ');
@endphp
<a href="{{ route('recipes.show', $recipe) }}" {{ $attributes->class('group relative block aspect-square overflow-hidden rounded-2xl') }}
   style="background: radial-gradient(circle at 25% 20%, oklch(85% 0.09 {{ $hue }} / .9), transparent 60%), linear-gradient(135deg, color-mix(in oklab, var(--meal-{{ $recipe->meal_type->value }}) 55%, var(--color-base-100)), oklch(75% 0.08 {{ ($hue + 40) % 360 }}))">
    @if ($recipe->image_url)
        <img src="{{ $recipe->image_url }}" class="absolute inset-0 size-full object-cover" alt="" loading="lazy">
    @else
        <span aria-hidden="true" class="absolute inset-0 grid place-items-center pb-8 font-[family-name:var(--font-display)] text-5xl font-semibold text-black/15 transition-transform group-hover:scale-110">{{ $initials }}</span>
    @endif

    <span class="absolute top-2 left-2 flex gap-1">
        @if ($minutes > 0)
            <span class="badge badge-sm border-0 bg-base-100/85">{{ $minutes }} min</span>
        @endif
        @if ($rating !== null)
            <span class="badge badge-sm border-0 bg-base-100/85" title="Average rating {{ number_format($rating, 1) }}"><span class="text-warning" aria-hidden="true">★</span> {{ number_format($rating, 1) }}</span>
        @endif
    </span>

    @if ($needs !== '')
        <span data-missing-badge class="absolute top-2.5 right-2.5 size-3 rounded-full bg-warning ring-2 ring-base-100" title="needs: {{ $needs }}"><span class="sr-only">needs: {{ $needs }}</span></span>
    @endif

    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/40 to-transparent p-3 pt-10">
        <span class="line-clamp-2 text-sm leading-tight font-semibold text-white">{{ $recipe->title }}</span>
        <span class="block truncate text-[11px] text-white/80">{{ collect([ucfirst($recipe->meal_type->value), $recipe->cuisine ? \Illuminate\Support\Str::ucfirst($recipe->cuisine) : null])->filter()->implode(' · ') }}</span>
    </span>
</a>
