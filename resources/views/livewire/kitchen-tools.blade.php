<div x-data="tileSelect" x-on:keydown.escape.window="selecting && toggleSelecting()">
    @include('livewire.partials.section-header', [
        'title' => 'Kitchen tools', 'filters' => \App\Enums\KitchenToolFilter::cases(), 'current' => $filter,
        'help' => 'Tap a tool to mark it owned or not. ⋯ opens its note and other names.',
    ])

    @if ($kinds->isEmpty())
        <p class="rounded-2xl bg-base-200 py-8 text-center text-sm text-base-content/70">{{ match ($filter) {
            \App\Enums\KitchenToolFilter::Owned => 'You have marked no kitchen tool owned yet.',
            \App\Enums\KitchenToolFilter::NotOwned => 'You have every kitchen tool the app knows.',
            \App\Enums\KitchenToolFilter::All => 'The app knows no kitchen tools yet.',
        } }}</p>
    @else
        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-5 xl:grid-cols-7">
            @foreach ($kinds as $kind)
                <li class="group/tile relative" wire:key="kind-{{ $kind->id }}" style="view-transition-name: tool-{{ $kind->id }}"
                    data-key-on="{{ $kind->name }}" data-key-off="{{ $kind->name }}">
                    {{-- The look follows aria-pressed, so a tap can flip it before the server answers. --}}
                    <button type="button"
                            class="group flex min-h-24 w-full flex-col items-center justify-center gap-1 rounded-xl border border-base-300 px-1 py-3 text-center transition aria-pressed:bg-base-100 aria-[pressed=false]:border-dashed aria-[pressed=false]:text-base-content/70"
                            :class="picked.includes({{ $kind->id }}) && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100'"
                            x-on:click="tap({{ $kind->id }}, () => $wire.toggleOwned({{ $kind->id }}), $el)"
                            aria-pressed="{{ $kind->owned ? 'true' : 'false' }}"
                            aria-label="Owned: {{ $kind->display_name }}">
                        <span aria-hidden="true" class="absolute top-1.5 left-1.5 hidden size-4 place-items-center rounded-full bg-primary text-[10px] text-primary-content group-aria-pressed:grid">✓</span>
                        <span class="size-7 group-aria-pressed:text-primary group-aria-[pressed=false]:opacity-50 [&>svg]:size-full" aria-hidden="true">{{ \App\Support\KitchenToolIcon::for($kind->name) }}</span>
                        <span class="text-xs leading-tight font-medium">{{ $kind->display_name }}</span>
                        @if ($kind->note)
                            <span class="line-clamp-1 text-[11px] text-base-content/70">{{ $kind->note }}</span>
                        @endif
                    </button>
                    {{-- Always shown on phones; on desktop it appears on hover or keyboard focus. --}}
                    <button type="button" class="btn btn-ghost btn-xs absolute top-0 right-0 min-h-11 min-w-11 text-base text-base-content/60 hover:text-base-content lg:opacity-0 lg:group-hover/tile:opacity-100 lg:focus-visible:opacity-100"
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

    @include('livewire.partials.tile-grid-bar', ['offLabel' => 'Mark not owned', 'onLabel' => 'Mark owned'])

    {{-- Details sheet: note, household other names, delete --}}
    @if ($detail)
        <div class="modal modal-open modal-bottom sm:modal-middle" role="dialog" aria-label="{{ $detail->display_name }} details"
             x-on:keydown.escape.window="$wire.closeDetails()">
            <div class="modal-box">
                <div class="flex items-center gap-3">
                    <span class="size-9 text-primary [&>svg]:size-full" aria-hidden="true">{{ \App\Support\KitchenToolIcon::for($detail->name) }}</span>
                    <h3 class="flex-1 text-lg font-semibold">{{ $detail->display_name }}</h3>
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
                    <p class="mt-1 text-xs text-base-content/70">For you only; never used to match recipes.</p>
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
