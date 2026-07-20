{{-- Shared recipe form fields (bound to InteractsWithRecipeForm props). Used by edit and create. --}}
<div class="grid grid-cols-12 gap-x-3 gap-y-4">
    <div class="col-span-12 md:col-span-6">
        <label class="mb-1 block text-sm font-medium" for="title">Title</label>
        <input id="title" type="text" class="input min-h-11 w-full @error('title') input-error @enderror" wire:model="title">
        @error('title') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
    <div class="col-span-6 md:col-span-3">
        <label class="mb-1 block text-sm font-medium" for="mealType">Meal</label>
        <select id="mealType" class="select min-h-11 w-full @error('mealType') select-error @enderror" wire:model="mealType">
            @foreach (\App\Enums\MealType::cases() as $type)
                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
            @endforeach
        </select>
        @error('mealType') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
    <div class="col-span-6 md:col-span-3">
        <label class="mb-1 block text-sm font-medium" for="cuisine">Cuisine</label>
        <input id="cuisine" type="text" class="input min-h-11 w-full @error('cuisine') input-error @enderror" wire:model="cuisine">
        @error('cuisine') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>

    <div class="col-span-12">
        <label class="mb-1 block text-sm font-medium" for="description">Description</label>
        <textarea id="description" rows="2" class="textarea w-full @error('description') textarea-error @enderror" wire:model="description"></textarea>
        @error('description') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>

    <div class="col-span-4 md:col-span-2">
        <label class="mb-1 block text-sm font-medium" for="prepMinutes">Prep (min)</label>
        <input id="prepMinutes" type="number" min="0" class="input min-h-11 w-full @error('prepMinutes') input-error @enderror" wire:model="prepMinutes">
        @error('prepMinutes') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
    <div class="col-span-4 md:col-span-2">
        <label class="mb-1 block text-sm font-medium" for="cookMinutes">Cook (min)</label>
        <input id="cookMinutes" type="number" min="0" class="input min-h-11 w-full @error('cookMinutes') input-error @enderror" wire:model="cookMinutes">
        @error('cookMinutes') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
    <div class="col-span-4 md:col-span-2">
        <label class="mb-1 block text-sm font-medium" for="servings">Servings</label>
        <input id="servings" type="number" min="1" class="input min-h-11 w-full @error('servings') input-error @enderror" wire:model="servings">
        @error('servings') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
    <div class="col-span-12 md:col-span-6">
        <label class="mb-1 block text-sm font-medium" for="sourceUrl">Source URL</label>
        <input id="sourceUrl" type="url" class="input min-h-11 w-full @error('sourceUrl') input-error @enderror" wire:model="sourceUrl">
        @error('sourceUrl') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>

    <div class="col-span-12">
        <label class="mb-1 block text-sm font-medium" for="tagsInput">Tags (comma separated)</label>
        <input id="tagsInput" type="text" class="input min-h-11 w-full @error('tagsInput') input-error @enderror" wire:model="tagsInput">
        @error('tagsInput') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>

    <div class="col-span-12">
        <label class="mb-1 block text-sm font-medium" for="instructions">Instructions (markdown)</label>
        <textarea id="instructions" rows="8" class="textarea w-full @error('instructions') textarea-error @enderror" wire:model="instructions"></textarea>
        @error('instructions') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
    </div>
</div>
