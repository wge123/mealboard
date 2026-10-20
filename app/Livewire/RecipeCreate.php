<?php

namespace App\Livewire;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ImportRecipePage;
use App\Actions\Recipes\ParsePastedIngredients;
use App\Discovery\ClaudeCliFailed;
use App\Discovery\ShapingPass;
use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Livewire\Concerns\InteractsWithRecipeForm;
use App\Models\Recipe;
use App\Support\HouseholdPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
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

    public string $link = '';

    public string $importError = '';

    /** Whether the form was filled from a recipe link: saving then makes an Imported recipe. */
    public bool $imported = false;

    public function importLink(): void
    {
        $this->importError = '';
        $link = trim($this->link);

        if ($link === '') {
            return;
        }

        if ($this->openLibraryRecipeAt($link)) {
            return;
        }

        $reading = app(ImportRecipePage::class)->handle($link);

        if ($reading->candidate === null) {
            $this->importError = $reading->refusal->message();

            return;
        }

        $candidate = $reading->candidate;

        // The page may name itself by another address than the one pasted.
        if ($this->openLibraryRecipeAt($candidate['source_url'])) {
            return;
        }

        $this->title = $candidate['title'];
        $this->description = $candidate['description'];
        $this->sourceUrl = $candidate['source_url'];
        $this->mealType = $candidate['meal_type'];
        $this->cuisine = $candidate['cuisine'] ?? '';
        // The reader returns 0 for a time the page leaves out. No model may guess it
        // (ADR 0004) and no sensible code estimate exists, so it stays blank and required.
        $this->prepMinutes = $candidate['prep_minutes'] > 0 ? $candidate['prep_minutes'] : null;
        $this->cookMinutes = $candidate['cook_minutes'] > 0 ? $candidate['cook_minutes'] : null;
        $this->servings = $candidate['servings'] > 0 ? $candidate['servings'] : $this->servings;
        $this->rows = [];
        $this->tools = [];
        $this->steps = [];
        $this->fillRows($candidate['ingredients']);
        $this->fillTools($candidate['tools']);
        $this->fillSteps($candidate['steps']);
        $this->imported = true;
        $this->link = '';
    }

    /**
     * Open the library recipe that already has this address, if any. A trailing
     * slash makes no difference. Returns whether it redirected.
     */
    private function openLibraryRecipeAt(string $address): bool
    {
        $bare = rtrim($address, '/');
        $existing = Recipe::query()->whereIn('source_url', [$bare, $bare.'/'])->first();

        if ($existing === null) {
            return false;
        }

        $this->redirect(route('recipes.show', $existing));

        return true;
    }

    /**
     * The library recipe whose title matches the form's title, ignoring case; a warning only.
     */
    #[Computed]
    public function sameTitleRecipe(): ?Recipe
    {
        $title = trim($this->title);

        return $title === '' ? null : Recipe::query()->whereRaw('lower(title) = ?', [mb_strtolower($title)])->first();
    }

    /**
     * The avoided ingredients the form's rows contain; a warning only, the recipe still saves.
     *
     * @return list<string>
     */
    #[Computed]
    public function avoidedInRows(): array
    {
        return app(HouseholdPreferences::class)->avoidedInNames(array_column($this->rows, 'name'));
    }

    public function mount(HouseholdPreferences $preferences): void
    {
        $this->mealType = MealType::Any->value;
        // A new recipe starts at the household size; editing keeps the recipe's own servings.
        $this->servings = $preferences->householdSize();
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
            [
                ...$this->recipeAttributes(),
                'source' => $this->imported ? RecipeSource::Imported : RecipeSource::Manual,
                // The household chose this recipe, so it joins the library approved.
                'status' => RecipeStatus::Approved,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ],
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
