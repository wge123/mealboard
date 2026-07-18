<div>
    <div class="mb-3">
        <a class="text-decoration-none" href="{{ route('recipes.index') }}">&larr; Recipes</a>
    </div>

    <h1 class="h3 mb-3">Add recipe</h1>

    <form wire:submit="save">
        @include('livewire.partials.recipe-form-fields')

        <h2 class="h5 mt-4">Paste a recipe</h2>
        <p class="text-muted small mb-2">One ingredient per line, e.g. <code>2 cups diced onion</code>. Parsed rows land below and stay editable.</p>
        <textarea rows="5" class="form-control mb-2" wire:model="paste" aria-label="Paste ingredients"></textarea>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" wire:click="parsePaste">Parse ingredients</button>

        <h2 class="h5">Ingredients</h2>
        @include('livewire.partials.ingredient-rows')

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save recipe</button>
            <a class="btn btn-outline-secondary" href="{{ route('recipes.index') }}">Cancel</a>
        </div>
    </form>
</div>
