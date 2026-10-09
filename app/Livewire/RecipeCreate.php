<?php

namespace App\Livewire;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ParsePastedIngredients;
use App\Discovery\ClaudeCliFailed;
use App\Discovery\ShapingPass;
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

    /** Which parser produced the current rows: '', 'ai', or 'heuristic'. */
    public string $parsedWith = '';

    public string $parseError = '';

    public function mount(): void
    {
        $this->mealType = MealType::Any->value;
        $this->addRow();
        $this->addTool();
        $this->addStep();
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
            $check = app(ShapingPass::class)->fromPastedText($this->paste);
        } catch (ClaudeCliFailed $e) {
            // The claude CLI is the boundary; show why it failed and fill nothing.
            report($e);
            $this->parseError = 'AI parse failed: '.$e->getMessage();

            return;
        }

        if (! $check->passes()) {
            $this->parseError = 'AI parse could not find kitchen tools, ingredients and cooking steps in the pasted text: '.implode('; ', $check->errors);

            return;
        }

        $parsed = $check->candidate;

        $this->fillRows($parsed['ingredients']);
        $this->fillTools($parsed['tools']);
        $this->fillSteps($parsed['steps']);
        $this->parsedWith = 'ai';
        $this->paste = '';
    }

    /**
     * Map parsed rows into form-row shape and append them, keeping any rows
     * already filled in (parsed rows replace blank ones only).
     *
     * @param  array<int, array{qty: ?float, unit: ?string, name: string, note?: ?string, prep_note?: ?string}>  $parsed
     */
    private function fillRows(array $parsed): void
    {
        $rows = array_map(fn (array $row) => [
            'name' => $row['name'],
            'qty' => $row['qty'] !== null ? (string) $row['qty'] : '',
            'unit' => $row['unit'] ?? '',
            'prep_note' => $row['prep_note'] ?? $row['note'] ?? '',
        ], $parsed);

        $this->discardBlankRows();
        $this->rows = array_merge($this->rows, $rows);
    }

    /**
     * @param  array<int, array{alternatives: array<int, string>, count: int}>  $parsed
     */
    private function fillTools(array $parsed): void
    {
        $this->discardBlankRows();
        $this->tools = array_merge($this->tools, array_map(fn (array $tool) => [
            'alternatives' => implode(', ', $tool['alternatives']),
            'count' => $tool['count'],
        ], $parsed));
    }

    /**
     * @param  array<int, string>  $parsed
     */
    private function fillSteps(array $parsed): void
    {
        $this->discardBlankRows();
        $this->steps = array_merge($this->steps, $parsed);
    }

    public function save(): void
    {
        $this->discardBlankRows();
        $this->validate();

        $recipe = app(CreateRecipe::class)->handle(
            [...$this->recipeAttributes(), 'source' => RecipeSource::Manual],
            $this->rows,
            $this->toolsPayload(),
            $this->stepsPayload(),
        );

        $this->redirect(route('recipes.show', $recipe));
    }

    public function render(): View
    {
        return view('livewire.recipe-create');
    }
}
