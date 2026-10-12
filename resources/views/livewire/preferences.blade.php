<div class="space-y-6">
    <div>
        <h2 class="font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight">Preferences</h2>
        <p class="mt-1 text-sm opacity-60">The rules daily discovery and auto-fill follow. A recipe you ask for is not bound by the weekday limits. A tap saves.</p>
    </div>

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

    <section>
        @include('livewire.partials.preset-tiles', [
            'key' => 'size', 'label' => 'Household size: how many people you cook for', 'presets' => $this::SIZE_PRESETS,
            'value' => $stored['size'], 'pick' => 'pickHouseholdSize', 'model' => 'householdSize', 'save' => 'saveHouseholdSize', 'suffix' => null,
        ])
        @error('householdSize') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </section>

    <section>
        <h3 class="text-sm font-medium">Avoided ingredients</h3>
        <p class="mb-2 text-sm opacity-60">Never in a meal: discovery drops any recipe containing one, auto-fill never plans one, and the picker marks them. Matched as whole words.</p>
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-6" aria-label="Avoided ingredients">
            @foreach ($avoidTiles as $word)
                @php $isAvoided = $avoided->contains($word); @endphp
                <li wire:key="avoid-{{ $word }}">
                    <button type="button" data-test="avoid-{{ $word }}" aria-pressed="{{ $isAvoided ? 'true' : 'false' }}"
                            class="flex min-h-11 w-full items-center gap-2 rounded-xl border px-3 text-left text-sm capitalize {{ $isAvoided ? 'border-error bg-error/10 text-error' : 'border-base-300 bg-base-100' }}"
                            wire:click="toggleAvoided(@js($word))">
                        <span aria-hidden="true" class="grid size-4 shrink-0 place-items-center rounded border {{ $isAvoided ? 'border-error bg-error text-error-content' : 'border-base-300' }} text-[10px]">{{ $isAvoided ? '✓' : '' }}</span>
                        <span class="truncate">{{ $word }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <form wire:submit="avoid" class="mt-3" data-test="avoid-form">
            <div class="flex gap-2">
                <input type="text" maxlength="255" placeholder="Something else, e.g. durian" aria-label="Ingredient to avoid"
                       class="input min-h-11 flex-1 {{ $errors->has('newAvoided') ? 'input-error' : '' }}"
                       wire:model="newAvoided">
                <button type="submit" class="btn btn-primary min-h-11"
                        wire:loading.attr="disabled" wire:target="avoid">Avoid</button>
            </div>
            @error('newAvoided') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
