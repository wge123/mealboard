<div>
    <a class="btn btn-ghost btn-sm mb-4 min-h-11 border border-base-300" href="{{ route('recipes.index') }}">
        &larr; Recipes
    </a>

    @if (! $editing)
        @php($minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes)

        {{-- Hero header: display title, status, chip row. --}}
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>
            <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" wire:click="startEditing">Edit</button>
        </div>

        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            <span class="badge badge-soft {{ ['pending' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-error', 'archived' => 'badge-neutral'][$recipe->status->value] }}">
                {{ ucfirst($recipe->status->value) }}
            </span>
            <span class="badge badge-ghost slot-label font-medium" style="--slot-accent: var(--meal-{{ $recipe->meal_type->value }})">
                {{ ucfirst($recipe->meal_type->value) }}
            </span>
            @if ($recipe->cuisine)
                <span class="badge badge-ghost">{{ ucfirst($recipe->cuisine) }}</span>
            @endif
            @if ($minutes > 0)
                <span class="badge badge-ghost" title="Prep {{ $recipe->prep_minutes }} min, cook {{ $recipe->cook_minutes }} min">{{ $minutes }} min</span>
            @endif
            <span class="badge badge-ghost">Serves {{ $recipe->servings }}</span>
            @foreach ($recipe->tags ?? [] as $tag)
                <span class="badge badge-ghost badge-sm">{{ $tag }}</span>
            @endforeach
        </div>

        <p class="mt-2 text-sm opacity-60">
            Inspired from <a class="link" href="{{ $recipe->source_url }}" target="_blank" rel="noopener">{{ parse_url($recipe->source_url, PHP_URL_HOST) ?: $recipe->source_url }}</a> · {{ ucfirst($recipe->source->value) }}
        </p>
        @if ($recipe->approved_at)
            <p class="text-sm opacity-60">
                Approved {{ $recipe->approved_at->format('Y-m-d') }}@if ($recipe->approvedBy) by {{ $recipe->approvedBy->name }}@endif
            </p>
        @endif

        @if ($recipe->description)
            <p class="mt-3 max-w-prose leading-relaxed">{{ $recipe->description }}</p>
        @endif

        {{-- Status controls: joined button group. --}}
        <div class="join mt-4">
            @if ($recipe->status !== \App\Enums\RecipeStatus::Approved)
                <button type="button" class="btn join-item btn-success btn-sm min-h-11" wire:click="setStatus('approved')"
                        wire:loading.attr="disabled" wire:target="setStatus">
                    <span wire:loading wire:target="setStatus('approved')" class="loading loading-spinner loading-xs"></span>
                    Approve
                </button>
            @endif
            @if ($recipe->status !== \App\Enums\RecipeStatus::Rejected)
                <button type="button" class="btn btn-outline join-item btn-error btn-sm min-h-11" wire:click="setStatus('rejected')"
                        wire:loading.attr="disabled" wire:target="setStatus">
                    <span wire:loading wire:target="setStatus('rejected')" class="loading loading-spinner loading-xs"></span>
                    Reject
                </button>
            @endif
            @if ($recipe->status !== \App\Enums\RecipeStatus::Archived)
                <button type="button" class="btn btn-ghost join-item btn-sm min-h-11 border border-base-300" wire:click="setStatus('archived')"
                        wire:loading.attr="disabled" wire:target="setStatus">
                    <span wire:loading wire:target="setStatus('archived')" class="loading loading-spinner loading-xs"></span>
                    Archive
                </button>
            @endif
        </div>

        {{-- Ingredients (left) + instructions (right) on lg+, stacked on phones. --}}
        <div class="mt-6 grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
            <section class="rounded-2xl border border-base-300 bg-base-100 lg:col-span-2">
                <h2 class="px-4 pt-4 font-[family-name:var(--font-display)] text-xl font-semibold">Ingredients</h2>
                <ul class="divide-y divide-base-300 p-2">
                    @forelse ($recipe->ingredients as $ingredient)
                        <li class="flex items-baseline gap-3 px-2 py-2.5">
                            <span class="w-16 shrink-0 text-right text-sm tabular-nums opacity-70">
                                {{ $ingredient->pivot->qty !== null ? (string) (float) $ingredient->pivot->qty : '' }}
                                {{ $ingredient->pivot->unit !== 'count' ? $ingredient->pivot->unit : '' }}
                            </span>
                            <span class="min-w-0">
                                <span class="font-medium">{{ $ingredient->name }}</span>
                                @if ($ingredient->is_pantry_staple)
                                    <span class="badge badge-ghost badge-xs ms-1 align-middle">staple</span>
                                @endif
                                @if ($ingredient->pivot->note)
                                    <span class="block text-xs opacity-50">{{ $ingredient->pivot->note }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="px-2 py-2.5 text-sm opacity-60">No ingredients recorded.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl border border-base-300 bg-base-100 p-4 lg:col-span-3 lg:p-5">
                <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Instructions</h2>
                <div class="prose-mb mt-3">
                    {!! \Illuminate\Support\Str::markdown($recipe->instructions, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </section>
        </div>
    @else
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Edit recipe</h1>

        <form wire:submit="save" class="mt-4">
            @include('livewire.partials.recipe-form-fields')

            <h2 class="mt-6 mb-2 font-[family-name:var(--font-display)] text-xl font-semibold">Ingredients</h2>
            @include('livewire.partials.ingredient-rows')

            <div class="mt-6 flex gap-2">
                <button type="submit" class="btn btn-primary min-h-11"
                        wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading wire:target="save" class="loading loading-spinner loading-xs"></span>
                    Save
                </button>
                <button type="button" class="btn btn-ghost min-h-11 border border-base-300" wire:click="cancelEditing">Cancel</button>
            </div>
        </form>
    @endif
</div>
