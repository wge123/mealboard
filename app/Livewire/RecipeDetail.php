<?php

namespace App\Livewire;

use App\Actions\Recipes\SetRecipeStatus;
use App\Actions\Recipes\UpdateRecipe;
use App\Enums\RecipeStatus;
use App\Livewire\Concerns\InteractsWithRecipeForm;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RecipeDetail extends Component
{
    use InteractsWithRecipeForm;

    public Recipe $recipe;

    public bool $editing = false;

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe->load('ingredients');
    }

    public function startEditing(): void
    {
        $this->title = $this->recipe->title;
        $this->description = $this->recipe->description;
        $this->sourceUrl = $this->recipe->source_url ?? '';
        $this->mealType = $this->recipe->meal_type->value;
        $this->cuisine = $this->recipe->cuisine ?? '';
        $this->prepMinutes = $this->recipe->prep_minutes;
        $this->cookMinutes = $this->recipe->cook_minutes;
        $this->servings = $this->recipe->servings;
        $this->instructions = $this->recipe->instructions;
        $this->tagsInput = implode(', ', $this->recipe->tags ?? []);
        $this->rows = $this->recipe->ingredients->map(fn ($ingredient) => [
            'name' => $ingredient->name,
            'qty' => $ingredient->pivot->qty !== null ? (string) (float) $ingredient->pivot->qty : '',
            'unit' => $ingredient->pivot->unit ?? '',
            'note' => $ingredient->pivot->note ?? '',
        ])->values()->all();

        $this->resetErrorBag();
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
    }

    public function save(): void
    {
        $this->discardBlankRows();
        $this->validate();

        $this->recipe = app(UpdateRecipe::class)->handle(
            $this->recipe,
            $this->recipeAttributes(),
            $this->rows,
        );

        $this->editing = false;
    }

    public function setStatus(string $status): void
    {
        $this->recipe = app(SetRecipeStatus::class)->handle(
            $this->recipe,
            RecipeStatus::from($status),
            auth()->user(),
        );
    }

    public function render(): View
    {
        return view('livewire.recipe-detail')
            ->title($this->recipe->title);
    }
}
