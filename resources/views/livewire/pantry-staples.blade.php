<div x-data="tileSelect" x-on:keydown.escape.window="selecting && toggleSelecting()">
    @include('livewire.partials.section-header', [
        'title' => 'Pantry staples', 'filters' => \App\Enums\PantryFilter::cases(), 'current' => $filter,
        'help' => 'What you keep in stock. Staples never go on the buy list or count toward the ingredient limit.',
    ])

    <label class="input mb-3 min-h-11 w-full sm:max-w-sm">
        <x-tabler-search class="size-4 text-base-content/70" />
        <input type="search" class="grow" placeholder="Search ingredients…"
               wire:model.live.debounce.300ms="search" aria-label="Search ingredients">
    </label>
    @error('markMany') <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

    @if ($ingredients->isEmpty())
        <div class="rounded-box border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-display text-xl font-semibold">No ingredients match</p>
            <p class="mt-1 text-sm text-base-content/70">Add a staple by name below, or search for another word.</p>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-5">
            @foreach ($ingredients as $ingredient)
                {{-- The keys order the tile the way render() does once tapped: staples by name, the rest by recipe count. --}}
                <li wire:key="ingredient-{{ $ingredient->id }}" style="view-transition-name: staple-{{ $ingredient->id }}"
                    data-key-on="{{ $ingredient->name }}" data-key-off="{{ sprintf('%06d %s', 999999 - $ingredient->recipes_count, $ingredient->name) }}">
                    <x-check-tile :label="$ingredient->name" :on="$ingredient->is_pantry_staple"
                                  ::class="picked.includes({{ $ingredient->id }}) && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100'"
                                  x-on:click="tap({{ $ingredient->id }}, () => $wire.toggle({{ $ingredient->id }}), $el)"
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

    @include('livewire.partials.tile-grid-bar', ['offLabel' => 'Remove staples', 'onLabel' => 'Make staples'])
</div>
