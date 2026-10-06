<?php

namespace App\Livewire;

use App\Actions\Planning\BuildShoppingList;
use App\Actions\Planning\RecordCartAvailability;
use App\Actions\Planning\SaveProductMatch;
use App\Enums\MealPlanStatus;
use App\Enums\Unit;
use App\Enums\WalmartAvailability;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\WalmartMatch;
use App\Support\CleanIngredientKeywords;
use App\Support\WalmartCartLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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

    /** Walmart's "Unable to add to Cart" list, pasted after a cart import. */
    public string $outOfStockPaste = '';

    /** The cart's shipping section, pasted after a cart import. */
    public string $shipOnlyPaste = '';

    /**
     * What the last recordAvailability() call flagged, for the confirmation line.
     *
     * @var array{out-of-stock: list<string>, ship-only: list<string>, ok: int}|null
     */
    public ?array $availabilityReport = null;

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
     * Record what the last cart import could not put in the pickup order. The
     * matches the cart link covered are the ones judged: flagged when a pasted
     * line names them, ok otherwise.
     */
    public function recordAvailability(): void
    {
        if (trim($this->outOfStockPaste) === '' && trim($this->shipOnlyPaste) === '') {
            $this->addError('availability', 'Paste the unavailable list or the shipping section first.');

            return;
        }

        $this->availabilityReport = app(RecordCartAvailability::class)->handle(
            $this->matches($this->itemNames($this->items())),
            $this->outOfStockPaste,
            $this->shipOnlyPaste,
        );

        $this->outOfStockPaste = '';
        $this->shipOnlyPaste = '';
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
        $items = $this->items();
        $links = $this->links($items);

        return view('livewire.shopping-list', [
            'items' => $items,
            'links' => $links,
            'cartUrl' => $this->cartUrl($links),
            'checked' => $this->mealPlan->checked_items ?? [],
            'markdown' => $this->markdownExport(),
            'plain' => $this->plainExport(),
        ]);
    }

    /**
     * One affiliate add-to-cart link covering every matched row, or null when
     * nothing is matched — unmatched rows keep their per-row search chip.
     *
     * @param  array<string, array{href: string, matched: bool, availability: WalmartAvailability|null, seenAt: string|null}>  $links
     */
    private function cartUrl(array $links): ?string
    {
        $productUrls = collect($links)
            ->filter(fn (array $link) => $link['matched'])
            ->pluck('href')
            ->values()
            ->all();

        return app(WalmartCartLink::class)->handle($productUrls);
    }

    /**
     * Walmart link per row: the remembered product URL when a match exists,
     * otherwise a search on the cleaned ingredient keywords.
     *
     * @param  array<string, list<array{name: string, qty: float|null, unit: string|null, notes: list<string>}>>  $items
     * @return array<string, array{href: string, matched: bool, availability: WalmartAvailability|null, seenAt: string|null}>
     */
    private function links(array $items): array
    {
        $names = $this->itemNames($items);

        $matched = $this->matches($names)
            ->keyBy(fn (WalmartMatch $match) => $match->ingredient->name);

        $clean = app(CleanIngredientKeywords::class);

        return $names->mapWithKeys(fn (string $name) => [
            $name => isset($matched[$name])
                ? [
                    'href' => $matched[$name]->product_url,
                    'matched' => true,
                    'availability' => $matched[$name]->availability,
                    'seenAt' => $matched[$name]->availability_seen_at?->format('j M'),
                ]
                : [
                    'href' => 'https://www.walmart.com/search?q='.urlencode($clean->handle($name)),
                    'matched' => false,
                    'availability' => null,
                    'seenAt' => null,
                ],
        ])->all();
    }

    /**
     * @param  array<string, list<array{name: string, qty: float|null, unit: string|null, notes: list<string>}>>  $items
     * @return Collection<int, string>
     */
    private function itemNames(array $items): Collection
    {
        return collect($items)->collapse()->pluck('name');
    }

    /**
     * @param  Collection<int, string>  $names
     * @return EloquentCollection<int, WalmartMatch>
     */
    private function matches(Collection $names): EloquentCollection
    {
        return WalmartMatch::query()
            ->whereHas('ingredient', fn ($query) => $query->whereIn('name', $names))
            ->with('ingredient')
            ->get();
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
