<div>
    @include('livewire.partials.section-header', [
        'title' => 'Preferences',
        'help' => 'The rules discovery and auto-fill follow; a recipe you ask for skips the weekday limits. A tap saves.',
    ])

    <section aria-labelledby="weekday-limits" class="space-y-4">
        <h3 id="weekday-limits" class="sr-only">Weekday limits</h3>
        @include('livewire.partials.preset-tiles', [
            'key' => 'minutes', 'label' => 'Weekday limits: minutes total', 'presets' => $this::MINUTE_PRESETS,
            'value' => $stored['minutes'], 'pick' => 'pickMinutes', 'model' => 'weekdayMinutes', 'save' => 'saveMinutes', 'suffix' => 'min',
        ])
        @error('weekdayMinutes') <p class="text-sm text-error">{{ $message }}</p> @enderror
        @include('livewire.partials.preset-tiles', [
            'key' => 'ingredients', 'label' => 'Weekday limits: ingredients (pantry staples don\'t count)', 'presets' => $this::INGREDIENT_PRESETS,
            'value' => $stored['ingredients'], 'pick' => 'pickIngredients', 'model' => 'weekdayIngredients', 'save' => 'saveIngredients', 'suffix' => null,
        ])
        @error('weekdayIngredients') <p class="text-sm text-error">{{ $message }}</p> @enderror
    </section>

    <section class="mt-6">
        @include('livewire.partials.preset-tiles', [
            'key' => 'size', 'label' => 'Household size: how many people you cook for', 'presets' => $this::SIZE_PRESETS,
            'value' => $stored['size'], 'pick' => 'pickHouseholdSize', 'model' => 'householdSize', 'save' => 'saveHouseholdSize', 'suffix' => null,
        ])
        @error('householdSize') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </section>

    <section class="mt-6">
        <h3 class="text-sm font-medium">Avoided ingredients</h3>
        <p class="mb-2 text-sm text-base-content/70">Recipes with these are never suggested or planned. Matched as whole words.</p>
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-5" aria-label="Avoided ingredients">
            @foreach ($avoidTiles as $word)
                <li wire:key="avoid-{{ $word }}">
                    <x-check-tile :label="Str::ucfirst($word)" :on="$avoided->contains($word)" tone="error"
                                  data-test="avoid-{{ $word }}" wire:click="toggleAvoided({{ \Illuminate\Support\Js::from($word) }})" />
                </li>
            @endforeach
        </ul>

        <form wire:submit="avoid" class="mt-5" data-test="avoid-form">
            <label for="new-avoided" class="mb-1 block text-sm font-medium">Avoid something else</label>
            <div class="flex gap-2">
                <input id="new-avoided" type="text" maxlength="255" placeholder="Durian"
                       class="input min-h-11 min-w-0 flex-1 {{ $errors->has('newAvoided') ? 'input-error' : '' }}"
                       wire:model="newAvoided">
                <button type="submit" class="btn btn-primary min-h-11"
                        wire:loading.attr="disabled" wire:target="avoid">Avoid</button>
            </div>
            @error('newAvoided') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
