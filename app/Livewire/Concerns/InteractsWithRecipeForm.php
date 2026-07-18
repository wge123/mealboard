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

    public string $instructions = '';

    public string $tagsInput = '';

    /** @var array<int, array{name: string, qty: string, unit: string, note: string}> */
    public array $rows = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'sourceUrl' => ['nullable', 'url', 'max:255'],
            'mealType' => ['required', Rule::enum(MealType::class)],
            'cuisine' => ['nullable', 'string', 'max:255'],
            'prepMinutes' => ['required', 'integer', 'min:0'],
            'cookMinutes' => ['required', 'integer', 'min:0'],
            'servings' => ['required', 'integer', 'min:1'],
            'instructions' => ['required', 'string'],
            'tagsInput' => ['nullable', 'string'],
            'rows' => ['array'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.qty' => ['nullable', 'numeric', 'min:0'],
            'rows.*.unit' => ['nullable', Rule::enum(Unit::class)],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->rows[] = ['name' => '', 'qty' => '', 'unit' => '', 'note' => ''];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    /**
     * Drop rows the user left entirely blank so they don't trip validation.
     */
    protected function discardBlankRows(): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row) => trim(implode('', $row)) !== '',
        ));
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
            'source_url' => $this->sourceUrl ?: null,
            'meal_type' => $this->mealType,
            'cuisine' => $this->cuisine !== '' ? mb_strtolower(trim($this->cuisine)) : null,
            'prep_minutes' => $this->prepMinutes,
            'cook_minutes' => $this->cookMinutes,
            'servings' => $this->servings,
            'instructions' => $this->instructions,
            'tags' => collect(explode(',', $this->tagsInput))
                ->map(fn ($tag) => mb_strtolower(trim($tag)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
