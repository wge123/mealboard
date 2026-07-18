{{-- Shared recipe form fields (bound to InteractsWithRecipeForm props). Used by edit and create. --}}
<div class="row g-3">
    <div class="col-12 col-md-8">
        <label class="form-label" for="title">Title</label>
        <input id="title" type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title">
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="mealType">Meal</label>
        <select id="mealType" class="form-select @error('mealType') is-invalid @enderror" wire:model="mealType">
            @foreach (\App\Enums\MealType::cases() as $type)
                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
            @endforeach
        </select>
        @error('mealType') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="cuisine">Cuisine</label>
        <input id="cuisine" type="text" class="form-control @error('cuisine') is-invalid @enderror" wire:model="cuisine">
        @error('cuisine') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" rows="2" class="form-control @error('description') is-invalid @enderror" wire:model="description"></textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-4 col-md-2">
        <label class="form-label" for="prepMinutes">Prep (min)</label>
        <input id="prepMinutes" type="number" min="0" class="form-control @error('prepMinutes') is-invalid @enderror" wire:model="prepMinutes">
        @error('prepMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-4 col-md-2">
        <label class="form-label" for="cookMinutes">Cook (min)</label>
        <input id="cookMinutes" type="number" min="0" class="form-control @error('cookMinutes') is-invalid @enderror" wire:model="cookMinutes">
        @error('cookMinutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-4 col-md-2">
        <label class="form-label" for="servings">Servings</label>
        <input id="servings" type="number" min="1" class="form-control @error('servings') is-invalid @enderror" wire:model="servings">
        @error('servings') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="sourceUrl">Source URL</label>
        <input id="sourceUrl" type="url" class="form-control @error('sourceUrl') is-invalid @enderror" wire:model="sourceUrl">
        @error('sourceUrl') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="tagsInput">Tags (comma separated)</label>
        <input id="tagsInput" type="text" class="form-control @error('tagsInput') is-invalid @enderror" wire:model="tagsInput">
        @error('tagsInput') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="instructions">Instructions (markdown)</label>
        <textarea id="instructions" rows="8" class="form-control @error('instructions') is-invalid @enderror" wire:model="instructions"></textarea>
        @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
