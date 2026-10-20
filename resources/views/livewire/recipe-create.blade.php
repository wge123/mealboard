<div>
    <x-ui.page-header title="Add recipe" :back="route('recipes.index')" />

    <x-ui.card title="Paste a recipe link" class="mb-4">
        <p class="text-base-content/70">
            Paste the address of a recipe page. The app reads the page's own recipe data and fills the form below,
            copied as the page has it. Check it, then save.
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <input type="url" class="input min-h-11 flex-1 sm:min-h-9" wire:model="link" wire:keydown.enter.prevent="importLink" placeholder="https://" aria-label="Recipe link">
            <x-ui.button variant="primary" wire:click="importLink" busy="importLink" busy-label="Reading&hellip;">Fill from link</x-ui.button>
            @if ($imported)
                <x-ui.badge tone="primary">Imported</x-ui.badge>
            @endif
        </div>
        @if ($importError !== '')
            <x-ui.alert tone="error" class="mt-3">{{ $importError }} You can still paste the recipe text below.</x-ui.alert>
        @endif
    </x-ui.card>

    <form wire:submit="save">
        {{-- Paste-to-parse: the fastest way in; heuristic and AI parsers side by side. --}}
        <x-ui.card title="Paste a recipe" class="mb-4">
            <p class="text-base-content/70">
                One ingredient per line, e.g. <code class="rounded bg-base-200 px-1">2 cups diced onion</code>,
                or paste anything and let AI parse it. Parsed rows land below and stay editable.
            </p>
            <textarea rows="6" class="textarea mt-3 w-full" wire:model="paste" aria-label="Paste a recipe"></textarea>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-ui.button wire:click="parsePaste" busy="parsePaste">Parse ingredients</x-ui.button>
                <x-ui.button variant="primary" wire:click="aiParse" busy="aiParse" busy-label="Parsing&hellip;">AI parse</x-ui.button>
                @if ($parsedWith === 'ai')
                    <x-ui.badge tone="primary">AI parsed</x-ui.badge>
                @elseif ($parsedWith === 'heuristic')
                    <x-ui.badge tone="neutral">Heuristic parsed</x-ui.badge>
                @endif
            </div>
            @if ($parseError !== '')
                <x-ui.alert tone="error" class="mt-3">{{ $parseError }}</x-ui.alert>
            @endif
        </x-ui.card>

        @if ($this->sameTitleRecipe)
            <x-ui.alert tone="warning" class="mb-4">
                You already have a recipe called "{{ $this->sameTitleRecipe->title }}". Another site's version may well be a different recipe, so you can still save this one.
            </x-ui.alert>
        @endif
        @if ($this->avoidedInRows !== [])
            <x-ui.alert tone="warning" class="mb-4">
                Contains an avoided ingredient: {{ implode(', ', $this->avoidedInRows) }}. You can still save it; auto-fill will not plan it.
            </x-ui.alert>
        @endif
        @if ($imported && ($prepMinutes === null || $cookMinutes === null))
            <x-ui.alert tone="info" class="mb-4">
                The page left out some times. Fill in prep and cook minutes before saving.
            </x-ui.alert>
        @endif

        @include('livewire.partials.recipe-form-fields')

        <div class="mt-6 flex gap-2">
            <x-ui.button variant="primary" type="submit" busy="save">Save recipe</x-ui.button>
            <x-ui.button :href="route('recipes.index')">Cancel</x-ui.button>
        </div>
    </form>
</div>
