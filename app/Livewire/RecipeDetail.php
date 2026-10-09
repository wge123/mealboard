<?php

namespace App\Livewire;

use App\Actions\Recipes\SetRecipeStatus;
use App\Actions\Recipes\UpdateRecipe;
use App\Enums\RecipeStatus;
use App\Livewire\Concerns\InteractsWithRecipeForm;
use App\Models\Recipe;
use App\Support\MissingKitchenTools;
use App\Support\MissingTool;
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
        $this->recipe = $recipe;
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
        $this->tagsInput = implode(', ', $this->recipe->tags ?? []);
        $this->rows = $this->loadedRecipe()->ingredients->map(fn ($ingredient) => [
            'name' => $ingredient->name,
            'qty' => $ingredient->pivot->qty !== null ? (string) (float) $ingredient->pivot->qty : '',
            'unit' => $ingredient->pivot->unit ?? '',
            'prep_note' => $ingredient->pivot->note ?? '',
        ])->values()->all();
        $this->tools = $this->loadedRecipe()->recipeTools->map(fn ($tool) => [
            'alternatives' => $tool->alternatives->pluck('word')->implode(', '),
            'count' => $tool->count,
        ])->values()->all();
        $this->steps = $this->loadedRecipe()->cookingSteps->pluck('text')->all();

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
            $this->toolsPayload(),
            $this->stepsPayload(),
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

    /** The recipe with every relation the page reads, loaded once (the write actions hand back a refreshed model). */
    private function loadedRecipe(): Recipe
    {
        return $this->recipe->loadMissing(['ingredients', 'recipeTools.alternatives', 'cookingSteps']);
    }

    public function render(): View
    {
        $this->loadedRecipe();

        $missingToolIds = collect(app(MissingKitchenTools::class)->for($this->recipe))
            ->mapWithKeys(fn (MissingTool $missing) => [$missing->tool->id => true]);

        return view('livewire.recipe-detail', ['missingToolIds' => $missingToolIds])
            ->title($this->recipe->title);
    }
}
