<?php

namespace App\Livewire;

use App\Actions\Recipes\SetRecipeStatus;
use App\Actions\Recipes\UpdateRecipe;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Enums\Unit;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RecipeDetail extends Component
{
    public Recipe $recipe;

    public bool $editing = false;

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

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe->load('ingredients');
    }

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

    public function addRow(): void
    {
        $this->rows[] = ['name' => '', 'qty' => '', 'unit' => '', 'note' => ''];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $this->validate();

        $this->recipe = app(UpdateRecipe::class)->handle($this->recipe, [
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
        ], $this->rows);

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
