<?php

namespace App\Livewire;

use App\Actions\Planning\BuildShoppingList;
use App\Enums\MealPlanStatus;
use App\Enums\Unit;
use App\Models\MealPlan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Shopping list')]
class ShoppingList extends Component
{
    public MealPlan $mealPlan;

    public bool $includeStaples = false;

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
     * @return array<string, list<array{name: string, qty: float|null, unit: string|null, notes: list<string>}>>
     */
    public function items(): array
    {
        return app(BuildShoppingList::class)->handle($this->mealPlan, $this->includeStaples);
    }

    /** Markdown checklist: category headings + `- [ ] qty unit name` lines. */
    public function markdownExport(): string
    {
        $sections = [];

        foreach ($this->items() as $category => $items) {
            $lines = ['## '.ucfirst($category)];

            foreach ($items as $item) {
                $lines[] = '- [ ] '.$this->itemLabel($item);
            }

            $sections[] = implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }

    /** Plain list, one `quantity unit name` line per item (Instacart handoff). */
    public function plainExport(): string
    {
        $lines = [];

        foreach ($this->items() as $items) {
            foreach ($items as $item) {
                $lines[] = $this->itemLabel($item);
            }
        }

        return implode("\n", $lines);
    }

    public function render(): View
    {
        return view('livewire.shopping-list', [
            'items' => $this->items(),
            'checked' => $this->mealPlan->checked_items ?? [],
            'markdown' => $this->markdownExport(),
            'plain' => $this->plainExport(),
        ]);
    }

    /**
     * @param  array{name: string, qty: float|null, unit: string|null, notes: list<string>}  $item
     */
    private function itemLabel(array $item): string
    {
        $parts = [];

        if ($item['qty'] !== null) {
            $parts[] = $this->formatQty($item['qty']);
        }

        // "3 count carrots" reads worse than "3 carrots" on a shopping list.
        if ($item['unit'] !== null && $item['unit'] !== Unit::Count->value) {
            $parts[] = $item['unit'];
        }

        $parts[] = $item['name'];

        return implode(' ', $parts);
    }

    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }
}
