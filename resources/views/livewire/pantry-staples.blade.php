@php
    // Each aisle is its own table, so its folding row pins under the top bar until the next aisle takes over.
    $aisles = $ingredients->groupBy(fn ($ingredient) => $ingredient->category?->value ?? 'other')->sortKeys();
@endphp
<div x-data="tileSelect" x-on:keydown.escape.window="selecting && toggleSelecting()">
    <x-ui.card title="Pantry staples" flush>
        <x-slot:actions>
            <x-ui.button x-on:click="toggleSelecting()" x-bind:aria-pressed="selecting" aria-pressed="false">
                <span x-text="selecting ? 'Cancel' : 'Select'">Select</span>
            </x-ui.button>
        </x-slot:actions>

        <p class="max-w-prose px-4 py-3 text-base-content/70">What you keep in stock. Staples never go on the buy list or count toward the ingredient limit.</p>

        <div class="flex flex-wrap items-end gap-x-4 gap-y-3 border-t border-base-300 px-4 py-3">
            <div class="w-full sm:w-64">
                <x-ui.field label="Search" name="search" id="search-ingredients" type="search" placeholder="Search ingredients…" aria-label="Search ingredients"
                            wire:model.live.debounce.300ms="search" />
            </div>
            <x-ui.segmented label="Show">
                @foreach (\App\Enums\PantryFilter::cases() as $case)
                    <x-ui.segmented.option :pressed="$filter === $case" wire:click="$set('filter', '{{ $case->value }}')">{{ $case->label() }}</x-ui.segmented.option>
                @endforeach
            </x-ui.segmented>
        </div>
        @error('markMany') <p class="px-4 pb-3 font-medium text-error" role="alert">{{ $message }}</p> @enderror

        <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy" wire:target="search,filter">
            @if ($ingredients->isEmpty())
                <x-ui.empty-state title="No ingredients match" class="border-t border-base-300">Add a staple by name below, or search for another word.</x-ui.empty-state>
            @else
                @foreach ($aisles as $aisle => $aisleIngredients)
                    @php $onHand = $aisleIngredients->where('is_pantry_staple', true)->count(); @endphp
                    <x-ui.table wire:key="aisle-{{ $aisle }}" data-resort-group class="border-t border-base-300">
                        <tbody>
                            <x-ui.table.group :label="ucfirst($aisle)" :cols="1" sticky="max-lg:top-[3.75rem] lg:top-14"><span data-on-count>{{ $onHand }}</span> of {{ $aisleIngredients->count() }} on hand</x-ui.table.group>
                            <x-ui.table.row :group="ucfirst($aisle)" class="hover:bg-transparent!">
                                <td class="p-0!">
                                    <ul class="gap-x-6 px-2 py-1 sm:columns-2 lg:columns-3">
                                        @foreach ($aisleIngredients as $ingredient)
                                            {{-- The keys order the line the way render() does once ticked: staples by name, the rest by recipe count. --}}
                                            <li wire:key="ingredient-{{ $ingredient->id }}" class="break-inside-avoid rounded-field" style="view-transition-name: staple-{{ $ingredient->id }}"
                                                :class="picked.includes({{ $ingredient->id }}) && 'bg-primary/10'"
                                                data-key-on="{{ $ingredient->name }}" data-key-off="{{ sprintf('%06d %s', 999999 - $ingredient->recipes_count, $ingredient->name) }}">
                                                <x-ui.checklist-line :id="'staple-'.$ingredient->id" :label="$ingredient->name" :checked="$ingredient->is_pantry_staple"
                                                                     :count="$ingredient->recipes_count" :count-label="'used in '.$ingredient->recipes_count.' '.\Illuminate\Support\Str::plural('recipe', $ingredient->recipes_count)"
                                                                     aria-label="Staple: {{ $ingredient->name }}"
                                                                     x-on:click="tap({{ $ingredient->id }}, () => $wire.toggle({{ $ingredient->id }}), $el, $event)" />
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </x-ui.table.row>
                        </tbody>
                    </x-ui.table>
                @endforeach

                @if ($truncated)
                    <p class="border-t border-base-300 px-4 py-3 text-base-content/70">Showing the {{ $limit }} most used. Search to find the rest.</p>
                @endif
            @endif
        </div>

        <x-slot:footer>
            <form wire:submit="addStaple" class="flex max-w-md items-end gap-2" data-test="add-staple-form">
                <div class="min-w-0 flex-1">
                    <x-ui.field label="Add a staple the list doesn't show" name="newStaple" id="new-staple" maxlength="255" placeholder="Cumin" wire:model="newStaple" />
                </div>
                <x-ui.button type="submit" variant="primary" busy="addStaple">Add</x-ui.button>
            </form>
        </x-slot:footer>
    </x-ui.card>

    @include('livewire.partials.tile-grid-bar', ['offLabel' => 'Remove staples', 'onLabel' => 'Make staples'])
</div>
