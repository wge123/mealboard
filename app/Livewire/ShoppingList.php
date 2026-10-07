<?php

namespace App\Livewire;

use App\Actions\Planning\BuildShoppingList;
use App\Actions\Planning\SaveProductMatch;
use App\Enums\MealPlanStatus;
use App\Models\Ingredient;
use App\Models\MealPlan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Shopping list')]
class ShoppingList extends Component
{
    public MealPlan $mealPlan;

    public bool $includeStaples = false;

    /**
     * Per-row "found it" paste fields, keyed by ingredient name.
     *
     * @var array<string, string>
     */
    public array $foundUrls = [];

    public function mount(MealPlan $mealPlan): void
    {
        abort_if(
            $mealPlan->status === MealPlanStatus::Draft,
            403,
            'The shopping list is available once the week is locked.',
        );

        $this->mealPlan = $mealPlan;
    }

    /**
     * Toggle a line's checked state. State lives on the meal plan, so it
     * survives reloads and is shared by both users.
     */
    public function toggleItem(string $key): void
    {
        $checked = $this->mealPlan->checked_items ?? [];

        $checked = in_array($key, $checked, true)
            ? array_values(array_diff($checked, [$key]))
            : [...$checked, $key];

        $this->mealPlan->update(['checked_items' => $checked]);
    }

    /**
     * Remember the pasted Walmart product URL for an ingredient. The product
     * name is the ingredient name for now (Tier 1 — richer names arrive with
     * the MCP handoff).
     */
    public function saveMatch(string $name): void
    {
        $url = trim($this->foundUrls[$name] ?? '');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError('foundUrls.'.$name, 'Paste a full product URL.');

            return;
        }

        app(SaveProductMatch::class)->handle(
            Ingredient::query()->where('name', $name)->firstOrFail(),
            $url,
            $name,
        );

        unset($this->foundUrls[$name]);
    }

    /**
     * The week's shopping list, merged once per request and shared by the
     * render and both exports.
     *
     * @return array{lines: array<string, list<array<string, mixed>>>, buy_list: list<array<string, mixed>>, cart_link: string|null}
     */
    #[Computed]
    public function shoppingList(): array
    {
        return app(BuildShoppingList::class)->handle($this->mealPlan, $this->includeStaples);
    }

    /** Markdown checklist: category headings + `- [ ] label` lines. */
    public function markdownExport(): string
    {
        $sections = [];

        foreach ($this->shoppingList['lines'] as $category => $lines) {
            $section = ['## '.ucfirst($category)];

            foreach ($lines as $line) {
                $section[] = '- [ ] '.$line['label'];
            }

            $sections[] = implode("\n", $section);
        }

        return implode("\n\n", $sections);
    }

    /** Plain list, one label per line (Instacart handoff). */
    public function plainExport(): string
    {
        $labels = [];

        foreach ($this->shoppingList['lines'] as $lines) {
            foreach ($lines as $line) {
                $labels[] = $line['label'];
            }
        }

        return implode("\n", $labels);
    }

    public function render(): View
    {
        return view('livewire.shopping-list', [
            'items' => $this->shoppingList['lines'],
            'cartUrl' => $this->shoppingList['cart_link'],
            'markdown' => $this->markdownExport(),
            'plain' => $this->plainExport(),
        ]);
    }
}
