<div x-data="tileSelect" x-on:keydown.escape.window="selecting && toggleSelecting()">
    <x-ui.card title="Kitchen tools" flush>
        <x-slot:actions>
            <x-ui.button x-on:click="toggleSelecting()" x-bind:aria-pressed="selecting" aria-pressed="false" x-bind:class="selecting && 'btn-primary'">
                <span x-text="selecting ? 'Cancel' : 'Select'">Select</span>
            </x-ui.button>
        </x-slot:actions>

        <p class="max-w-prose px-4 py-3 text-base-content/70">Tap a tool to mark it owned or not. The dots button opens its note and other names. The number is how many recipes call for the tool.</p>

        <div class="flex flex-wrap items-end gap-x-4 gap-y-3 border-t border-base-300 px-4 py-3">
            <x-ui.segmented label="Show">
                @foreach (\App\Enums\KitchenToolFilter::cases() as $case)
                    <x-ui.segmented.option :pressed="$filter === $case" wire:click="$set('filter', '{{ $case->value }}')">{{ $case->label() }}</x-ui.segmented.option>
                @endforeach
            </x-ui.segmented>
        </div>
        @error('markMany') <p class="px-4 pb-3 font-medium text-error" role="alert">{{ $message }}</p> @enderror
        @error('deleteKind') <p class="px-4 pb-3 font-medium text-error" role="alert">{{ $message }}</p> @enderror

        <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy" wire:target="filter">
            @if ($kinds->isEmpty())
                <x-ui.empty-state class="border-t border-base-300" :title="match ($filter) {
                    \App\Enums\KitchenToolFilter::Owned => 'You have marked no kitchen tool owned yet.',
                    \App\Enums\KitchenToolFilter::NotOwned => 'You have every kitchen tool the app knows.',
                    \App\Enums\KitchenToolFilter::All => 'The app knows no kitchen tools yet.',
                }" />
            @else
                {{-- One list in columns under an Owned and a Don't have heading. The headings carry data-state, so a tap re-sorts a tool across them (tileSelect). --}}
                <ul class="gap-x-6 border-t border-base-300 px-2 pb-1 sm:columns-2 lg:columns-3 [&>li[data-state]]:[column-span:all]" data-resort-group>
                    @foreach ([true, false] as $isOwned)
                        @php $group = $kinds->where('owned', $isOwned); @endphp
                        @continue($group->isEmpty())
                        <li class="-mx-2 flex min-h-11 items-center gap-2 bg-base-200 px-4 text-xs font-semibold tracking-wide text-base-content/70 uppercase sm:min-h-9 {{ $isOwned ? '' : 'mt-1 border-t border-base-300' }}"
                            wire:key="group-{{ $isOwned ? 'owned' : 'missing' }}" data-state="{{ $isOwned ? 'on' : 'off' }}" data-key-on="" data-key-off="">
                            {{ $isOwned ? 'Owned' : "Don't have" }}
                            <span class="font-normal tracking-normal normal-case tabular-nums" @if ($isOwned) data-on-count @else data-off-count @endif>{{ $group->count() }}</span>
                        </li>
                        @foreach ($group as $kind)
                            @php
                                $uses = $recipeCounts[$kind->id] ?? 0;
                                $missing = ! $kind->owned && $uses > 0;
                                $usedIn = 'used in '.$uses.' '.\Illuminate\Support\Str::plural('recipe', $uses);
                            @endphp
                            <li class="group/tile break-inside-avoid rounded-field" wire:key="kind-{{ $kind->id }}" style="view-transition-name: tool-{{ $kind->id }}"
                                :class="picked.includes({{ $kind->id }}) && 'bg-primary/10'"
                                data-key-on="{{ $kind->name }}" data-key-off="{{ $kind->name }}">
                                <x-ui.checklist-line :id="'tool-'.$kind->id" :label="$kind->display_name" :checked="$kind->owned"
                                                     :hint="$kind->note" :count="$uses" :count-label="$missing ? $usedIn.', and you don\'t have it' : $usedIn" :warn="$missing"
                                                     aria-label="Owned: {{ $kind->display_name }}"
                                                     x-on:click="tap({{ $kind->id }}, () => $wire.toggleOwned({{ $kind->id }}), $el, $event)">
                                    <x-slot:leading>{{ \App\Support\KitchenToolIcon::for($kind->name) }}</x-slot:leading>
                                    <x-slot:action>
                                        {{-- Always shown on phones; on desktop it appears on hover or keyboard focus. --}}
                                        <x-ui.button square variant="ghost" icon="tabler-dots" :label="'Details for '.$kind->display_name"
                                                     class="sm:size-8 sm:min-h-8 lg:opacity-0 lg:group-hover/tile:opacity-100 lg:focus-visible:opacity-100"
                                                     x-show="! selecting" wire:click="openDetails({{ $kind->id }})" />
                                    </x-slot:action>
                                </x-ui.checklist-line>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </div>

        <x-slot:footer>
            <form wire:submit="addKind" class="flex max-w-md items-end gap-2" data-test="add-kind-form">
                <div class="min-w-0 flex-1">
                    <x-ui.field label="Add a kitchen tool the app doesn't know" name="newKind" id="new-kind" :error="$errors->first('addKind')" placeholder="Tortilla press" wire:model="newKind" />
                </div>
                <x-ui.button type="submit" variant="primary" busy="addKind">Add</x-ui.button>
            </form>
        </x-slot:footer>
    </x-ui.card>

    @include('livewire.partials.tile-grid-bar', ['offLabel' => 'Mark not owned', 'onLabel' => 'Mark owned'])

    {{-- Details sheet: owned toggle, note, other names, delete --}}
    @if ($detail)
        <div class="modal modal-open modal-bottom sm:modal-middle" role="dialog" aria-label="{{ $detail->display_name }} details"
             x-on:keydown.escape.window="$wire.closeDetails()">
            <div class="modal-box [scrollbar-gutter:stable]">
                <div class="flex items-center gap-3">
                    <span class="size-9 text-primary [&>svg]:size-full" aria-hidden="true">{{ \App\Support\KitchenToolIcon::for($detail->name) }}</span>
                    <h3 class="flex-1 text-lg font-semibold">{{ $detail->display_name }}</h3>
                    <button type="button" class="btn btn-circle btn-ghost min-h-11 min-w-11" wire:click="closeDetails" aria-label="Close"><x-tabler-x class="size-5" /></button>
                </div>

                <label class="mt-4 flex min-h-11 cursor-pointer items-center justify-between gap-3">
                    <span class="text-sm font-medium">I have this</span>
                    <input type="checkbox" class="toggle toggle-success"
                           data-test="detail-owned" @checked($detail->owned)
                           wire:click="toggleOwned({{ $detail->id }})">
                </label>

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

                <h4 class="mt-4 mb-1 text-sm font-medium">Other names</h4>
                @if ($detail->otherNames->isEmpty())
                    <p class="text-sm text-base-content/70">No other names yet. Add one so recipes that call it something else still match.</p>
                @else
                    <ul class="flex flex-wrap gap-2" aria-label="Other names for {{ $detail->display_name }}">
                        @foreach ($detail->otherNames as $otherName)
                            <li class="badge badge-outline h-auto gap-1 py-1" wire:key="other-name-{{ $otherName->id }}">
                                <span>{{ $otherName->name }}</span>
                                @if ($otherName->origin === \App\Enums\KitchenToolOrigin::Household)
                                    <button type="button" class="btn btn-ghost btn-xs min-h-11 min-w-11"
                                            data-test="remove-other-name-{{ $otherName->id }}"
                                            wire:click="removeOtherName({{ $otherName->id }})"
                                            aria-label="Remove other name {{ $otherName->name }}"><x-tabler-x class="size-4" /></button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @error('removeOtherName') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror

                <form class="mt-2 flex gap-2" wire:submit="addOtherName({{ $detail->id }})" data-test="add-other-name-form">
                    <input type="text" maxlength="255" class="input min-h-11 min-w-0 flex-1"
                           placeholder="Another name for it"
                           aria-label="Add an other name for {{ $detail->display_name }}"
                           wire:model="newOtherName">
                    <button type="submit" class="btn min-h-11">Add</button>
                </form>
                @error('addOtherName') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror

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
