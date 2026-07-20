<div>
    <a class="btn btn-ghost btn-sm mb-4 min-h-11 border border-base-300" href="{{ route('recipes.index') }}">
        &larr; Recipes
    </a>

    <h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Add recipe</h1>

    <form wire:submit="save">
        @include('livewire.partials.recipe-form-fields')

        {{-- Paste-to-parse: the fastest way in; heuristic and AI parsers side by side. --}}
        <section class="mt-6 rounded-2xl border border-base-300 bg-base-200 p-4">
            <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Paste a recipe</h2>
            <p class="mt-1 text-sm opacity-60">
                One ingredient per line, e.g. <code class="rounded bg-base-300 px-1">2 cups diced onion</code> —
                or paste anything and let AI parse it. Parsed rows land below and stay editable.
            </p>
            <textarea rows="6" class="textarea textarea-lg mt-3 w-full" wire:model="paste" aria-label="Paste ingredients"></textarea>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-outline btn-sm min-h-11" wire:click="parsePaste"
                        wire:loading.attr="disabled" wire:target="parsePaste">
                    <span wire:loading wire:target="parsePaste" class="loading loading-spinner loading-xs"></span>
                    Parse ingredients
                </button>
                <button type="button" class="btn btn-primary btn-sm min-h-11" wire:click="aiParse"
                        wire:loading.attr="disabled" wire:target="aiParse">
                    <span wire:loading.remove wire:target="aiParse">AI parse</span>
                    <span wire:loading.flex wire:target="aiParse" class="items-center gap-2">
                        <span class="loading loading-spinner loading-xs"></span>
                        Parsing&hellip;
                    </span>
                </button>
                @if ($parsedWith === 'ai')
                    <span class="badge badge-soft badge-success">AI parsed</span>
                @elseif ($parsedWith === 'heuristic')
                    <span class="badge badge-soft badge-neutral">Heuristic parsed</span>
                @elseif ($parsedWith === 'fallback')
                    <span class="badge badge-soft badge-warning">Heuristic parsed (AI unavailable)</span>
                @endif
            </div>
            @if ($parseError !== '')
                <div class="alert alert-error mt-3 py-2 text-sm" role="alert">{{ $parseError }}</div>
            @endif
        </section>

        <h2 class="mt-6 mb-2 font-[family-name:var(--font-display)] text-xl font-semibold">Ingredients</h2>
        @include('livewire.partials.ingredient-rows')

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="loading loading-spinner loading-xs"></span>
                Save recipe
            </button>
            <a class="btn btn-ghost min-h-11 border border-base-300" href="{{ route('recipes.index') }}">Cancel</a>
        </div>
    </form>
</div>
