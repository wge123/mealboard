<?php

namespace App\Livewire\Concerns;

use App\Enums\MealType;
use App\Enums\Unit;
use Illuminate\Validation\Rule;

/**
 * Shared recipe form state for the edit (RecipeDetail) and create (RecipeCreate)
 * Livewire components.
 */
trait InteractsWithRecipeForm
{
    public string $title = '';

    public string $description = '';

    public string $sourceUrl = '';

    public string $mealType = '';

    public string $cuisine = '';

    public ?int $prepMinutes = null;

    public ?int $cookMinutes = null;

    public ?int $servings = null;

    public string $tagsInput = '';

    /** @var array<int, array{name: string, qty: string, unit: string, prep_note: string}> */
    public array $rows = [];

    /** Alternatives are one comma-separated string per tool, split on save. @var array<int, array{alternatives: string, count: int|string}> */
    public array $tools = [];

    /** @var array<int, string> */
    public array $steps = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'sourceUrl' => ['required', 'url', 'max:255'],
            'mealType' => ['required', Rule::enum(MealType::class)],
            'cuisine' => ['nullable', 'string', 'max:255'],
            'prepMinutes' => ['required', 'integer', 'min:0'],
            'cookMinutes' => ['required', 'integer', 'min:0'],
            'servings' => ['required', 'integer', 'min:1'],
            'tagsInput' => ['nullable', 'string'],
            'tools' => ['required', 'array', 'min:1'],
            'tools.*.alternatives' => ['required', 'string', 'max:255'],
            'tools.*.count' => ['required', 'integer', 'min:1'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.qty' => ['nullable', 'numeric', 'min:0'],
            'rows.*.unit' => ['nullable', Rule::enum(Unit::class)],
            'rows.*.prep_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->rows[] = ['name' => '', 'qty' => '', 'unit' => '', 'prep_note' => ''];
    }

    public function addTool(): void
    {
        $this->tools[] = ['alternatives' => '', 'count' => 1];
    }

    public function removeTool(int $index): void
    {
        unset($this->tools[$index]);
        $this->tools = array_values($this->tools);
    }

    public function addStep(): void
    {
        $this->steps[] = '';
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    /**
     * Drop rows, tools and steps the user left entirely blank so they don't
     * trip validation (a blank tool still has its default count of 1).
     */
    protected function discardBlankRows(): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row) => trim(implode('', $row)) !== '',
        ));
        $this->tools = array_values(array_filter(
            $this->tools,
            fn (array $tool) => trim((string) $tool['alternatives']) !== '',
        ));
        $this->steps = array_values(array_filter(
            $this->steps,
            fn (string $step) => trim($step) !== '',
        ));
    }

    /**
     * The tools payload for CreateRecipe/UpdateRecipe: alternatives split out of the comma-separated input.
     *
     * @return array<int, array{alternatives: array<int, string>, count: int}>
     */
    protected function toolsPayload(): array
    {
        return array_map(fn (array $tool) => [
            'alternatives' => array_values(array_filter(array_map('trim', explode(',', $tool['alternatives'])))),
            'count' => (int) $tool['count'],
        ], $this->tools);
    }

    /**
     * @return array<int, string>
     */
    protected function stepsPayload(): array
    {
        return array_map(fn (string $step) => trim($step), $this->steps);
    }

    /**
     * The recipe attribute payload shared by create and update.
     *
     * @return array<string, mixed>
     */
    protected function recipeAttributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'source_url' => $this->sourceUrl,
            'meal_type' => $this->mealType,
            'cuisine' => $this->cuisine !== '' ? mb_strtolower(trim($this->cuisine)) : null,
            'prep_minutes' => $this->prepMinutes,
            'cook_minutes' => $this->cookMinutes,
            'servings' => $this->servings,
            'tags' => collect(explode(',', $this->tagsInput))
                ->map(fn ($tag) => mb_strtolower(trim($tag)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
