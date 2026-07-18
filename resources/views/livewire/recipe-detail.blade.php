<div>
    <div class="mb-3">
        <a class="text-decoration-none" href="{{ route('recipes.index') }}">&larr; Recipes</a>
    </div>

    @if (! $editing)
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <h1 class="h3 mb-0">{{ $recipe->title }}</h1>
            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="startEditing">Edit</button>
        </div>

        <p class="mb-2">
            <span class="badge text-bg-secondary">{{ ucfirst($recipe->status->value) }}</span>
            <span class="badge text-bg-light border">{{ ucfirst($recipe->meal_type->value) }}</span>
            @if ($recipe->cuisine)
                <span class="badge text-bg-light border">{{ ucfirst($recipe->cuisine) }}</span>
            @endif
            @foreach ($recipe->tags ?? [] as $tag)
                <span class="badge text-bg-light border">{{ $tag }}</span>
            @endforeach
        </p>

        <p class="text-muted mb-1">
            Prep {{ $recipe->prep_minutes }} min · Cook {{ $recipe->cook_minutes }} min ·
            {{ $recipe->prep_minutes + $recipe->cook_minutes }} min total · Serves {{ $recipe->servings }}
        </p>
        <p class="text-muted small mb-1">
            Source: {{ ucfirst($recipe->source->value) }}@if ($recipe->source_url) — <a href="{{ $recipe->source_url }}">{{ $recipe->source_url }}</a>@endif
        </p>
        @if ($recipe->approved_at)
            <p class="text-muted small">
                Approved {{ $recipe->approved_at->format('Y-m-d') }}@if ($recipe->approvedBy) by {{ $recipe->approvedBy->name }}@endif
            </p>
        @endif

        <p class="mt-3">{{ $recipe->description }}</p>

        <div class="mb-3">
            @if ($recipe->status !== \App\Enums\RecipeStatus::Approved)
                <button type="button" class="btn btn-success btn-sm" wire:click="setStatus('approved')">Approve</button>
            @endif
            @if ($recipe->status !== \App\Enums\RecipeStatus::Rejected)
                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="setStatus('rejected')">Reject</button>
            @endif
            @if ($recipe->status !== \App\Enums\RecipeStatus::Archived)
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setStatus('archived')">Archive</button>
            @endif
        </div>

        <h2 class="h5">Ingredients</h2>
        <div class="table-responsive mb-4">
            <table class="table table-sm align-middle">
                <tbody>
                    @forelse ($recipe->ingredients as $ingredient)
                        <tr>
                            <td class="text-nowrap text-end" style="width: 1%;">
                                {{ $ingredient->pivot->qty !== null ? (string) (float) $ingredient->pivot->qty : '' }}
                            </td>
                            <td class="text-nowrap" style="width: 1%;">{{ $ingredient->pivot->unit }}</td>
                            <td>{{ $ingredient->name }}</td>
                            <td class="text-muted">{{ $ingredient->pivot->note }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">No ingredients recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 class="h5">Instructions</h2>
        <div class="recipe-instructions">
            {!! \Illuminate\Support\Str::markdown($recipe->instructions, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    @else
        <h1 class="h3 mb-3">Edit recipe</h1>

        <form wire:submit="save">
            @include('livewire.partials.recipe-form-fields')

            <h2 class="h5 mt-4">Ingredients</h2>
            @include('livewire.partials.ingredient-rows')

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="cancelEditing">Cancel</button>
            </div>
        </form>
    @endif
</div>
