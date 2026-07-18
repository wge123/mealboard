<?php

namespace App\Livewire;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ParsePastedIngredients;
use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Livewire\Concerns\InteractsWithRecipeForm;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Add recipe')]
class RecipeCreate extends Component
{
    use InteractsWithRecipeForm;

    public string $paste = '';

    public function mount(): void
    {
        $this->mealType = MealType::Any->value;
        $this->addRow();
    }

    public function parsePaste(): void
    {
        if (trim($this->paste) === '') {
            return;
        }

        $parsed = array_map(fn (array $row) => [
            'name' => $row['name'],
            'qty' => $row['qty'] !== null ? (string) $row['qty'] : '',
            'unit' => $row['unit'] ?? '',
            'note' => $row['note'] ?? '',
        ], app(ParsePastedIngredients::class)->handle($this->paste));

        // Keep any rows already filled in; parsed rows replace blank ones.
        $this->discardBlankRows();
        $this->rows = array_merge($this->rows, $parsed);
        $this->paste = '';
    }

    public function save(): void
    {
        $this->discardBlankRows();
        $this->validate();

        $recipe = app(CreateRecipe::class)->handle(
            [...$this->recipeAttributes(), 'source' => RecipeSource::Manual],
            $this->rows,
        );

        $this->redirect(route('recipes.show', $recipe));
    }

    public function render(): View
    {
        return view('livewire.recipe-create');
    }
}
