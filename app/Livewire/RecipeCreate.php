<?php

namespace App\Livewire;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ParsePastedIngredients;
use App\Actions\Recipes\ParsePastedRecipeWithAi;
use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Livewire\Concerns\InteractsWithRecipeForm;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Add recipe')]
class RecipeCreate extends Component
{
    use InteractsWithRecipeForm;

    public string $paste = '';

    /** Which parser produced the current rows: '', 'ai', 'heuristic', or 'fallback'. */
    public string $parsedWith = '';

    public string $parseError = '';

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

        $this->parseError = '';
        $this->fillRows(app(ParsePastedIngredients::class)->handle($this->paste));
        $this->parsedWith = 'heuristic';
        $this->paste = '';
    }

    public function aiParse(): void
    {
        if (trim($this->paste) === '') {
            return;
        }

        $this->parseError = '';

        try {
            $parsed = app(ParsePastedRecipeWithAi::class)->handle($this->paste);
        } catch (Throwable $e) {
            // External boundary (local claude CLI): degrade visibly to the
            // heuristic parser — error shown, failure reported, never silent.
            report($e);
            $this->parseError = 'AI parse failed — used the heuristic parser instead. ('.$e->getMessage().')';
            $this->fillRows(app(ParsePastedIngredients::class)->handle($this->paste));
            $this->parsedWith = 'fallback';
            $this->paste = '';

            return;
        }

        $this->fillRows($parsed['ingredients']);
        $this->instructions = $parsed['instructions'];
        $this->parsedWith = 'ai';
        $this->paste = '';
    }

    /**
     * Map parsed rows into form-row shape and append them, keeping any rows
     * already filled in (parsed rows replace blank ones only).
     *
     * @param  array<int, array{qty: ?float, unit: ?string, name: string, note: ?string}>  $parsed
     */
    private function fillRows(array $parsed): void
    {
        $rows = array_map(fn (array $row) => [
            'name' => $row['name'],
            'qty' => $row['qty'] !== null ? (string) $row['qty'] : '',
            'unit' => $row['unit'] ?? '',
            'note' => $row['note'] ?? '',
        ], $parsed);

        $this->discardBlankRows();
        $this->rows = array_merge($this->rows, $rows);
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
