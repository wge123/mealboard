@php
    // A picture per kind, matched on a word in its name; the first match wins.
    $icons = [
        'knife' => '🔪', 'board' => '🪵', 'grater' => '🧀', 'peeler' => '🥕', 'mortar' => '🫙', 'scale' => '⚖️',
        'measuring' => '🥄', 'bowls' => '🥣', 'colander' => '🍝', 'rolling' => '🥖', 'can opener' => '🥫',
        'toaster' => '🍞', 'dutch oven' => '🍲', 'oven' => '🔥', 'wok' => '🥡', 'stovetop' => '🔥', 'pot' => '🍲',
        'griddle' => '🥞', 'steamer' => '♨️', 'cake' => '🎂', 'loaf' => '🍞', 'muffin' => '🧁', 'pie' => '🥧',
        'rack' => '🧇', 'sheet' => '🍪', 'baking' => '🥘', 'pan' => '🍳', 'skillet' => '🍳', 'fryer' => '🍟',
        'blender' => '🥤', 'processor' => '⚙️', 'mixer' => '🌀', 'microwave' => '📡', 'rice' => '🍚', 'cooker' => '🍲',
        'waffle' => '🧇', 'thermometer' => '🌡️', 'ladle' => '🥄', 'spatula' => '🍳', 'tongs' => '🥢', 'whisk' => '🥚',
        'spoon' => '🥄',
    ];
    $iconFor = fn (string $name) => collect($icons)->first(fn ($icon, $word) => str_contains($name, $word)) ?? '🍴';
@endphp

<div x-data="{ selecting: false, picked: [] }"
     x-on:keydown.escape.window="selecting = false; picked = []">
    <div class="mb-1 flex flex-wrap items-end justify-between gap-2">
        <h2 class="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight">Kitchen tools</h2>
        <div class="flex gap-2">
            <div class="join" role="group" aria-label="Show">
                @foreach (['all' => 'All', 'owned' => 'Owned', 'missing' => 'Missing'] as $value => $label)
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
    <p class="mb-4 text-sm opacity-60">Tap a tile to mark it owned or missing. <span aria-hidden="true">⋯</span> opens a tool's note and other names.</p>

    @if ($kinds->isEmpty())
        <p class="rounded-2xl bg-base-200 py-8 text-center text-sm opacity-60">No {{ $filter }} kitchen tools.</p>
    @else
        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-5 xl:grid-cols-7">
            @foreach ($kinds as $kind)
                <li class="relative" wire:key="kind-{{ $kind->id }}">
                    <button type="button"
                            class="flex min-h-24 w-full flex-col items-center justify-center gap-1 rounded-xl border px-1 py-3 text-center transition {{ $kind->owned ? 'border-base-300 bg-base-100' : 'border-transparent bg-base-200 opacity-60' }}"
                            :class="picked.includes({{ $kind->id }}) && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100 opacity-100'"
                            x-on:click="selecting
                                ? (picked.includes({{ $kind->id }}) ? picked = picked.filter(id => id !== {{ $kind->id }}) : picked.push({{ $kind->id }}))
                                : $wire.toggleOwned({{ $kind->id }})"
                            aria-pressed="{{ $kind->owned ? 'true' : 'false' }}"
                            aria-label="Owned: {{ $kind->display_name }}">
                        <span class="text-2xl" aria-hidden="true">{{ $iconFor($kind->name) }}</span>
                        <span class="text-xs leading-tight font-medium">{{ $kind->display_name }}</span>
                        @if ($kind->note)
                            <span class="line-clamp-1 text-[11px] opacity-60">{{ $kind->note }}</span>
                        @elseif (! $kind->owned)
                            <span class="text-[10px] font-semibold uppercase opacity-60">Missing</span>
                        @endif
                    </button>
                    <button type="button" class="btn btn-ghost btn-xs absolute top-0 right-0 min-h-11 min-w-11 opacity-50 hover:opacity-100"
                            x-show="! selecting"
                            wire:click="openDetails({{ $kind->id }})"
                            aria-label="Details for {{ $kind->display_name }}">⋯</button>
                </li>
            @endforeach
        </ul>
    @endif

    @error('markMany') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    @error('deleteKind') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

    <form wire:submit="addKind" class="mt-5" data-test="add-kind-form">
        <label for="new-kind" class="mb-1 block text-sm font-medium">Add a kitchen tool the app doesn't know</label>
        <div class="flex gap-2">
            <input id="new-kind" type="text" wire:model="newKind" placeholder="Tortilla press"
                   class="input min-h-11 flex-1">
            <button type="submit" class="btn btn-primary min-h-11">Add</button>
        </div>
        @error('addKind') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    </form>

    {{-- Select mode: what to do with the picked tiles --}}
    <div x-show="selecting" x-cloak x-transition
         class="fixed inset-x-4 bottom-24 z-40 mx-auto flex max-w-md items-center gap-2 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl lg:bottom-8">
        <span class="flex-1 px-2 text-sm"><b x-text="picked.length">0</b> picked</span>
        <button type="button" class="btn btn-sm min-h-11" :disabled="picked.length === 0"
                x-on:click="$wire.markMany(picked, false).then(() => { selecting = false; picked = [] })">Missing</button>
        <button type="button" class="btn btn-primary btn-sm min-h-11" :disabled="picked.length === 0"
                x-on:click="$wire.markMany(picked, true).then(() => { selecting = false; picked = [] })">Mark owned</button>
    </div>

    {{-- Details sheet: note, household other names, delete --}}
    @if ($detail)
        <div class="modal modal-open modal-bottom sm:modal-middle" role="dialog" aria-label="{{ $detail->display_name }} details"
             x-on:keydown.escape.window="$wire.closeDetails()">
            <div class="modal-box">
                <div class="flex items-center gap-3">
                    <span class="text-3xl" aria-hidden="true">{{ $iconFor($detail->name) }}</span>
                    <h3 class="flex-1 font-[family-name:var(--font-display)] text-xl font-semibold">{{ $detail->display_name }}</h3>
                    <button type="button" class="btn btn-circle btn-ghost min-h-11 min-w-11" wire:click="closeDetails" aria-label="Close">&times;</button>
                </div>

                <form class="mt-4" wire:submit="saveNote({{ $detail->id }})">
                    <label for="note-{{ $detail->id }}" class="mb-1 block text-sm font-medium">Note</label>
                    <div class="flex gap-2">
                        <input id="note-{{ $detail->id }}" type="text" maxlength="255"
                               class="input min-h-11 min-w-0 flex-1"
                               placeholder="e.g. 12-inch, cast iron"
                               aria-label="Note for {{ $detail->display_name }}"
                               wire:model="notes.{{ $detail->id }}">
                        <button type="submit" class="btn min-h-11">Save</button>
                    </div>
                    <p class="mt-1 text-xs opacity-60">For you only; never used to match recipes.</p>
                </form>

                @if ($detail->otherNames->isNotEmpty())
                    <h4 class="mt-4 mb-1 text-sm font-medium">Other names you added</h4>
                    <ul class="flex flex-wrap gap-2" aria-label="Other names for {{ $detail->display_name }}">
                        @foreach ($detail->otherNames as $otherName)
                            <li class="badge badge-outline h-auto gap-1 py-1" wire:key="other-name-{{ $otherName->id }}">
                                <span>{{ $otherName->name }}</span>
                                <button type="button" class="btn btn-ghost btn-xs min-h-11 min-w-11"
                                        data-test="remove-other-name-{{ $otherName->id }}"
                                        wire:click="removeOtherName({{ $otherName->id }})"
                                        aria-label="Remove other name {{ $otherName->name }}">&times;</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @error('removeOtherName') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

                @if ($detail->origin === \App\Enums\KitchenToolOrigin::Household)
                    <div class="mt-6 border-t border-base-300 pt-4">
                        <button type="button" class="btn btn-ghost min-h-11 text-error"
                                data-test="delete-kind-{{ $detail->id }}"
                                wire:click="deleteKind({{ $detail->id }})"
                                wire:confirm="Delete {{ $detail->display_name }}?">Delete this tool</button>
                    </div>
                @endif
            </div>
            <button type="button" class="modal-backdrop" wire:click="closeDetails" aria-label="Close details"></button>
        </div>
    @endif
</div>
