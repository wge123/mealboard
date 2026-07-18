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
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label" for="title">Title</label>
                    <input id="title" type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title">
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="mealType">Meal</label>
                    <select id="mealType" class="form-select @error('mealType') is-invalid @enderror" wire:model="mealType">
                        @foreach (\App\Enums\MealType::cases() as $type)
                            <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                        @endforeach
                    </select>
                    @error('mealType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="cuisine">Cuisine</label>
                    <input id="cuisine" type="text" class="form-control @error('cuisine') is-invalid @enderror" wire:model="cuisine">
                    @error('cuisine') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" rows="2" class="form-control @error('description') is-invalid @enderror" wire:model="description"></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-4 col-md-2">
                    <label class="form-label" for="prepMinutes">Prep (min)</label>
                    <input id="prepMinutes" type="number" min="0" class="form-control @error('prepMinutes') is-invalid @enderror" wire:model="prepMinutes">
                    @error('prepMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label" for="cookMinutes">Cook (min)</label>
                    <input id="cookMinutes" type="number" min="0" class="form-control @error('cookMinutes') is-invalid @enderror" wire:model="cookMinutes">
                    @error('cookMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label" for="servings">Servings</label>
                    <input id="servings" type="number" min="1" class="form-control @error('servings') is-invalid @enderror" wire:model="servings">
                    @error('servings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="sourceUrl">Source URL</label>
                    <input id="sourceUrl" type="url" class="form-control @error('sourceUrl') is-invalid @enderror" wire:model="sourceUrl">
                    @error('sourceUrl') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="tagsInput">Tags (comma separated)</label>
                    <input id="tagsInput" type="text" class="form-control @error('tagsInput') is-invalid @enderror" wire:model="tagsInput">
                    @error('tagsInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="instructions">Instructions (markdown)</label>
                    <textarea id="instructions" rows="8" class="form-control @error('instructions') is-invalid @enderror" wire:model="instructions"></textarea>
                    @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h2 class="h5 mt-4">Ingredients</h2>
            @include('livewire.partials.ingredient-rows')

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="cancelEditing">Cancel</button>
            </div>
        </form>
    @endif
</div>
