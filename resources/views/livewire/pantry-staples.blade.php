<div x-data="{ selecting: false, picked: [] }"
     x-on:keydown.escape.window="selecting = false; picked = []">
    <div class="mb-1 flex flex-wrap items-end justify-between gap-2">
        <h2 class="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight">Pantry staples</h2>
        <div class="flex gap-2">
            <div class="join" role="group" aria-label="Show">
                @foreach (['all' => 'All', 'staples' => 'On hand', 'others' => 'Everything else'] as $value => $label)
                    <button type="button" class="btn join-item btn-sm min-h-11 {{ $filter === $value ? 'btn-neutral' : '' }}"
                            wire:click="$set('filter', '{{ $value }}')"
                            aria-pressed="{{ $filter === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm min-h-11" :class="selecting && 'btn-primary'"
                    x-on:click="selecting = ! selecting; picked = []"
                    x-text="selecting ? 'Cancel' : 'Select'">Select</button>
        </div>
    </div>
    <p class="mb-4 text-sm opacity-60">What you keep in stock. Staples never land on the buy list and don't count toward the weekday ingredient limit. Tap a tile to switch it.</p>

    <div class="mb-3 flex flex-col gap-2 sm:flex-row">
        <input type="search" class="input min-h-11 flex-1" placeholder="Search ingredients…"
               wire:model.live.debounce.300ms="search" aria-label="Search ingredients">
        <form wire:submit="addStaple" class="flex flex-1 gap-2" data-test="add-staple-form">
            <input id="new-staple" type="text" maxlength="255" placeholder="Add a staple, e.g. cumin" aria-label="Add a staple by name"
                   class="input min-h-11 min-w-0 flex-1 {{ $errors->has('newStaple') ? 'input-error' : '' }}"
                   wire:model="newStaple">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="addStaple">Add</button>
        </form>
    </div>
    @error('newStaple') <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    @error('markMany') <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

    @if ($ingredients->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No ingredients match</p>
            <p class="mt-1 text-sm opacity-60">Add a staple by name above, or search for another word.</p>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-6">
            @foreach ($ingredients as $ingredient)
                <li wire:key="ingredient-{{ $ingredient->id }}">
                    <button type="button"
                            class="flex min-h-12 w-full items-center gap-2 rounded-xl border px-3 text-left text-sm {{ $ingredient->is_pantry_staple ? 'border-success bg-success/10 font-medium' : 'border-base-300 bg-base-100 opacity-80' }}"
                            :class="picked.includes({{ $ingredient->id }}) && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100'"
                            x-on:click="selecting
                                ? (picked.includes({{ $ingredient->id }}) ? picked = picked.filter(id => id !== {{ $ingredient->id }}) : picked.push({{ $ingredient->id }}))
                                : $wire.toggle({{ $ingredient->id }})"
                            aria-pressed="{{ $ingredient->is_pantry_staple ? 'true' : 'false' }}"
                            aria-label="Staple: {{ $ingredient->name }}">
                        <span aria-hidden="true" class="grid size-4 shrink-0 place-items-center rounded border text-[10px] {{ $ingredient->is_pantry_staple ? 'border-success bg-success text-success-content' : 'border-base-300' }}">{{ $ingredient->is_pantry_staple ? '✓' : '' }}</span>
                        <span class="truncate">{{ $ingredient->name }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($truncated)
            <p class="mt-2 text-sm opacity-60">Showing the first of the rest. Search to find more.</p>
        @endif
    @endif

    {{-- Select mode: what to do with the picked tiles --}}
    <div x-show="selecting" x-cloak x-transition
         class="fixed inset-x-4 bottom-24 z-40 mx-auto flex max-w-md items-center gap-2 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl lg:bottom-8">
        <span class="flex-1 px-2 text-sm"><b x-text="picked.length">0</b> picked</span>
        <button type="button" class="btn btn-sm min-h-11" :disabled="picked.length === 0"
                x-on:click="$wire.markMany(picked, false).then(() => { selecting = false; picked = [] })">Not staples</button>
        <button type="button" class="btn btn-primary btn-sm min-h-11" :disabled="picked.length === 0"
                x-on:click="$wire.markMany(picked, true).then(() => { selecting = false; picked = [] })">Make staples</button>
    </div>
</div>
