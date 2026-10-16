{{-- Shared recipe form fields (bound to InteractsWithRecipeForm props): the Details card, then one titled card per list. Used by edit and create. --}}
<x-ui.card title="Details">
    <div class="grid grid-cols-12 gap-x-3 gap-y-4">
        <div class="col-span-12 md:col-span-6">
            <x-ui.field label="Title" name="title" wire:model="title" />
        </div>

        <div class="col-span-6 md:col-span-3">
            <x-ui.field label="Meal" name="mealType">
                <select id="mealType" name="mealType" class="select min-h-11 w-full sm:min-h-9 @error('mealType') select-error @enderror" wire:model="mealType">
                    @foreach (\App\Enums\MealType::cases() as $type)
                        <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <div class="col-span-6 md:col-span-3">
            <x-ui.field label="Cuisine" name="cuisine" wire:model="cuisine" />
        </div>

        <div class="col-span-12">
            <x-ui.field label="Description" name="description">
                <textarea id="description" name="description" rows="2" class="textarea w-full @error('description') textarea-error @enderror" wire:model="description"></textarea>
            </x-ui.field>
        </div>

        <div class="col-span-4 md:col-span-2">
            <x-ui.field label="Prep (min)" name="prepMinutes" type="number" min="0" wire:model="prepMinutes" />
        </div>
        <div class="col-span-4 md:col-span-2">
            <x-ui.field label="Cook (min)" name="cookMinutes" type="number" min="0" wire:model="cookMinutes" />
        </div>
        <div class="col-span-4 md:col-span-2">
            <x-ui.field label="Servings" name="servings" type="number" min="1" wire:model="servings" />
        </div>
        <div class="col-span-12 md:col-span-6">
            <x-ui.field label="Source URL" name="sourceUrl" type="url" wire:model="sourceUrl" />
        </div>

        <div class="col-span-12">
            <x-ui.field label="Tags" name="tagsInput" hint="Separate tags with commas." wire:model="tagsInput" />
        </div>
    </div>
</x-ui.card>

<x-ui.card flush title="Kitchen tools" :count="count($tools)" class="mt-4">
    @include('livewire.partials.recipe-tool-rows')
</x-ui.card>

<x-ui.card flush title="Ingredients" :count="count($rows)" class="mt-4">
    @include('livewire.partials.ingredient-rows')
</x-ui.card>

<x-ui.card flush title="Cooking steps" :count="count($steps)" class="mt-4">
    @include('livewire.partials.cooking-step-rows')
</x-ui.card>
