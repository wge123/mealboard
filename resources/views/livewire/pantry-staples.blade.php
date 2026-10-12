<div x-data="tileSelect" x-on:keydown.escape.window="selecting && toggleSelecting()">
    @include('livewire.partials.section-header', [
        'title' => 'Pantry staples', 'filters' => \App\Enums\PantryFilter::cases(), 'current' => $filter,
        'help' => 'What you keep in stock. Staples never go on the buy list or count toward the ingredient limit.',
    ])

    <label class="input mb-3 min-h-11 w-full sm:max-w-sm">
        <svg class="h-4 w-4 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/></svg>
        <input type="search" class="grow" placeholder="Search ingredients…"
               wire:model.live.debounce.300ms="search" aria-label="Search ingredients">
    </label>
    @error('markMany') <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

    @if ($ingredients->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No ingredients match</p>
            <p class="mt-1 text-sm text-base-content/70">Add a staple by name below, or search for another word.</p>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-5">
            @foreach ($ingredients as $ingredient)
                <li wire:key="ingredient-{{ $ingredient->id }}">
                    <x-check-tile :label="$ingredient->name" :on="$ingredient->is_pantry_staple"
                                  ::class="picked.includes({{ $ingredient->id }}) && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100'"
                                  x-on:click="tap({{ $ingredient->id }}, () => $wire.toggle({{ $ingredient->id }}))"
                                  aria-label="Staple: {{ $ingredient->name }}" />
                </li>
            @endforeach
        </ul>

        @if ($truncated)
            <p class="mt-2 text-sm text-base-content/70">Showing the {{ $limit }} most used. Search to find the rest.</p>
        @endif
    @endif

    <form wire:submit="addStaple" class="mt-5" data-test="add-staple-form">
        <label for="new-staple" class="mb-1 block text-sm font-medium">Add a staple the list doesn't show</label>
        <div class="flex gap-2">
            <input id="new-staple" type="text" maxlength="255" placeholder="Cumin"
                   class="input min-h-11 min-w-0 flex-1 {{ $errors->has('newStaple') ? 'input-error' : '' }}"
                   wire:model="newStaple">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="addStaple">Add</button>
        </div>
        @error('newStaple') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    </form>

    @include('livewire.partials.tile-grid-bar', ['offLabel' => 'Not staples', 'onLabel' => 'Make staples'])
</div>
