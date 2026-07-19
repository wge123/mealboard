<div>
    <div class="mb-3">
        <a class="text-decoration-none" href="{{ route('recipes.index') }}">&larr; Recipes</a>
    </div>

    <h1 class="h3 mb-3">Add recipe</h1>

    <form wire:submit="save">
        @include('livewire.partials.recipe-form-fields')

        <h2 class="h5 mt-4">Paste a recipe</h2>
        <p class="text-muted small mb-2">One ingredient per line, e.g. <code>2 cups diced onion</code> — or paste anything and let AI parse it. Parsed rows land below and stay editable.</p>
        <textarea rows="5" class="form-control mb-2" wire:model="paste" aria-label="Paste ingredients"></textarea>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="parsePaste">Parse ingredients</button>
            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="aiParse"
                    wire:loading.attr="disabled" wire:target="aiParse">
                <span wire:loading.remove wire:target="aiParse">AI parse</span>
                <span wire:loading wire:target="aiParse">Parsing&hellip;</span>
            </button>
            @if ($parsedWith === 'ai')
                <span class="badge text-bg-success">AI parsed</span>
            @elseif ($parsedWith === 'heuristic')
                <span class="badge text-bg-secondary">Heuristic parsed</span>
            @elseif ($parsedWith === 'fallback')
                <span class="badge text-bg-warning">Heuristic parsed (AI unavailable)</span>
            @endif
        </div>
        @if ($parseError !== '')
            <div class="alert alert-danger py-2" role="alert">{{ $parseError }}</div>
        @endif

        <h2 class="h5">Ingredients</h2>
        @include('livewire.partials.ingredient-rows')

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save recipe</button>
            <a class="btn btn-outline-secondary" href="{{ route('recipes.index') }}">Cancel</a>
        </div>
    </form>
</div>
