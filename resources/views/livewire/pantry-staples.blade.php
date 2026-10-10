<x-settings.layout>
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Pantry staples</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">What you keep in stock. Staples never land on the buy list and don't count toward the weekday ingredient limit. Staples come first.</p>

    <form wire:submit="addStaple" class="mb-4" data-test="add-staple-form">
        <label for="new-staple" class="mb-1 block text-sm font-medium">Add a staple by name</label>
        <div class="flex gap-2">
            <input id="new-staple" type="text" maxlength="255" placeholder="soy sauce"
                   class="input min-h-11 flex-1 {{ $errors->has('newStaple') ? 'input-error' : '' }}"
                   wire:model="newStaple">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="addStaple">Add</button>
        </div>
        @error('newStaple') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    </form>

    <input type="search" class="input mb-3 min-h-11 w-full" placeholder="Search ingredients…"
           wire:model.live.debounce.300ms="search" aria-label="Search ingredients">

    @if ($ingredients->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No ingredients match</p>
            <p class="mt-1 text-sm opacity-60">Add a staple by name above, or search for another word.</p>
        </div>
    @else
        <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
            @foreach ($ingredients as $ingredient)
                <li class="flex items-center gap-3 px-4 py-3" wire:key="ingredient-{{ $ingredient->id }}">
                    <span class="min-w-0 flex-1 truncate font-medium {{ $ingredient->is_pantry_staple ? '' : 'opacity-60' }}">{{ $ingredient->name }}</span>

                    {{-- Staple toggle: one tap, 44 px target --}}
                    <label class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2">
                        <span class="text-xs opacity-60">{{ $ingredient->is_pantry_staple ? 'Staple' : 'Not a staple' }}</span>
                        <input type="checkbox" class="toggle toggle-success"
                               aria-label="Staple: {{ $ingredient->name }}"
                               @checked($ingredient->is_pantry_staple)
                               wire:click="toggle({{ $ingredient->id }})">
                    </label>
                </li>
            @endforeach
        </ul>

        @if ($truncated)
            <p class="mt-2 text-sm opacity-60">Showing the first {{ $ingredients->count() }}. Search to find the rest.</p>
        @endif
    @endif
</x-settings.layout>
