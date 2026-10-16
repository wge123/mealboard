@php
    $rows = [
        ['key' => 'minutes', 'label' => 'Weekday time limit', 'help' => 'Prep and cook, in total.', 'presets' => $this::MINUTE_PRESETS, 'value' => $stored['minutes'], 'pick' => 'pickMinutes', 'model' => 'weekdayMinutes', 'save' => 'saveMinutes', 'suffix' => 'min'],
        ['key' => 'ingredients', 'label' => 'Weekday ingredient limit', 'help' => "Pantry staples don't count.", 'presets' => $this::INGREDIENT_PRESETS, 'value' => $stored['ingredients'], 'pick' => 'pickIngredients', 'model' => 'weekdayIngredients', 'save' => 'saveIngredients', 'suffix' => 'items'],
        ['key' => 'size', 'label' => 'Household size', 'help' => 'How many people you cook for.', 'presets' => $this::SIZE_PRESETS, 'value' => $stored['size'], 'pick' => 'pickHouseholdSize', 'model' => 'householdSize', 'save' => 'saveHouseholdSize', 'suffix' => 'people'],
    ];
@endphp

<x-ui.card title="Preferences" flush>
    <p class="max-w-prose px-4 py-3 text-base-content/70">Weekday limits and household size are the rules discovery and auto-fill follow; a recipe you ask for skips the limits. A tap saves.</p>

    <div class="border-t border-base-300">
        @foreach ($rows as $row)
            @php $isPreset = in_array((int) $row['value'], $row['presets'], true); @endphp
            <x-ui.summary-row :id="$row['key']" :label="$row['label']" :value="$row['value']" :suffix="$row['suffix']" :help="$row['help']" :open="$errors->has($row['model'])">
                <div x-data="{ other: {{ $errors->has($row['model']) ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($row['presets'] as $preset)
                            <x-ui.chip data-test="{{ $row['key'] }}-{{ $preset }}" :pressed="(int) $row['value'] === $preset"
                                       wire:click="{{ $row['pick'] }}({{ $preset }})" x-on:click="other = false" class="tabular-nums">{{ $preset }}</x-ui.chip>
                        @endforeach
                        <x-ui.chip data-test="{{ $row['key'] }}-other" :pressed="! $isPreset" x-on:click="other = ! other"
                                   :class="$isPreset ? 'border-dashed' : ''">Other</x-ui.chip>
                    </div>
                    <form x-show="other" x-cloak wire:submit="{{ $row['save'] }}" x-on:submit="other = false" class="mt-3 flex gap-2">
                        <input type="number" min="1" step="1" inputmode="numeric" aria-label="{{ $row['label'] }}, other value"
                               class="input min-h-11 w-28 sm:min-h-9" wire:model="{{ $row['model'] }}">
                        <x-ui.button type="submit" variant="primary" busy="{{ $row['save'] }}">Save</x-ui.button>
                    </form>
                    @error($row['model']) <p class="mt-2 font-medium text-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </x-ui.summary-row>
        @endforeach
    </div>

    <div class="border-t border-base-300 px-4 py-4">
        <h3 class="font-medium">Avoided ingredients</h3>
        <p class="text-base-content/70">Recipes with these are never suggested or planned. Matched as whole words.</p>
        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Avoided ingredients">
            @foreach ($avoidTiles->sortBy(fn ($word) => $avoided->contains($word) ? 0 : 1) as $word)
                @php $on = $avoided->contains($word); @endphp
                <li wire:key="avoid-{{ $word }}">
                    <x-ui.chip tone="ink" data-test="avoid-{{ $word }}" :pressed="$on" :icon="$on ? 'tabler-x' : 'tabler-plus'"
                               wire:click="toggleAvoided({{ \Illuminate\Support\Js::from($word) }})">{{ Str::ucfirst($word) }}</x-ui.chip>
                </li>
            @endforeach
        </ul>

        <form wire:submit="avoid" class="mt-4 flex max-w-sm items-end gap-2" data-test="avoid-form">
            <div class="min-w-0 flex-1">
                <x-ui.field label="Avoid something else" name="newAvoided" id="new-avoided" maxlength="255" placeholder="Durian" wire:model="newAvoided" />
            </div>
            <x-ui.button type="submit" busy="avoid">Avoid</x-ui.button>
        </form>
    </div>
</x-ui.card>
