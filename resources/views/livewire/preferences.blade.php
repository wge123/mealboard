<x-settings.layout>
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Preferences</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">The rules daily discovery and auto-fill follow. A recipe you ask for is not bound by the weekday limits.</p>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
        <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Weekday limits</h2>
        <p class="mt-1 text-sm opacity-60">The most a recipe found by daily discovery may take. Pantry staples don't count toward the ingredients.</p>

        <form wire:submit="saveLimits" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <div>
                <label for="weekday-minutes" class="mb-1 block text-sm font-medium">Minutes total</label>
                <input id="weekday-minutes" type="number" min="1" step="1" inputmode="numeric"
                       class="input min-h-11 w-full {{ $errors->has('weekdayMinutes') ? 'input-error' : '' }}"
                       wire:model="weekdayMinutes">
                @error('weekdayMinutes') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="weekday-ingredients" class="mb-1 block text-sm font-medium">Ingredients</label>
                <input id="weekday-ingredients" type="number" min="1" step="1" inputmode="numeric"
                       class="input min-h-11 w-full {{ $errors->has('weekdayIngredients') ? 'input-error' : '' }}"
                       wire:model="weekdayIngredients">
                @error('weekdayIngredients') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2 sm:col-span-1 sm:self-end">
                <button type="submit" class="btn btn-primary min-h-11 w-full sm:w-auto"
                        wire:loading.attr="disabled" wire:target="saveLimits">
                    <span wire:loading wire:target="saveLimits" class="loading loading-spinner loading-xs"></span>
                    Save limits
                </button>
            </div>
        </form>
    </section>

    <section class="mt-4 rounded-2xl border border-base-300 bg-base-100 p-4">
        <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Household size</h2>
        <p class="mt-1 text-sm opacity-60">How many people you cook for. Discovery looks for recipes that serve about this many, and a new recipe starts at it.</p>

        <form wire:submit="saveHouseholdSize" class="mt-3 flex gap-2">
            <input type="number" min="1" step="1" inputmode="numeric" aria-label="Household size"
                   class="input min-h-11 w-28 {{ $errors->has('householdSize') ? 'input-error' : '' }}"
                   wire:model="householdSize">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="saveHouseholdSize">
                <span wire:loading wire:target="saveHouseholdSize" class="loading loading-spinner loading-xs"></span>
                Save size
            </button>
        </form>
        @error('householdSize') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </section>

    <section class="mt-4 rounded-2xl border border-base-300 bg-base-100 p-4">
        <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Avoided ingredients</h2>
        <p class="mt-1 text-sm opacity-60">Never in a meal: discovery drops any recipe containing one, auto-fill never plans one, and the picker marks them. Matched as whole words in ingredient names.</p>

        <form wire:submit="avoid" class="mt-3" data-test="avoid-form">
            <div class="flex gap-2">
                <input type="text" maxlength="255" placeholder="cilantro" aria-label="Ingredient to avoid"
                       class="input min-h-11 flex-1 {{ $errors->has('newAvoided') ? 'input-error' : '' }}"
                       wire:model="newAvoided">
                <button type="submit" class="btn btn-primary min-h-11"
                        wire:loading.attr="disabled" wire:target="avoid">Avoid</button>
            </div>
            @error('newAvoided') <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p> @enderror
        </form>

        @if ($avoided->isEmpty())
            <p class="mt-3 text-sm opacity-60">Nothing avoided yet.</p>
        @else
            <ul class="mt-3 flex flex-wrap gap-2" aria-label="Avoided ingredients">
                @foreach ($avoided as $word)
                    <li class="badge badge-outline h-auto gap-1 py-1" wire:key="avoided-{{ $word }}">
                        <span>{{ $word }}</span>
                        <button type="button" class="btn btn-ghost btn-xs min-h-11 min-w-11"
                                data-test="stop-avoiding-{{ $word }}"
                                wire:click="stopAvoiding(@js($word))"
                                aria-label="Stop avoiding {{ $word }}">&times;</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-settings.layout>
