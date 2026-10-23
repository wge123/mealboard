<?php

namespace App\Livewire;

use App\Actions\Planning\BuildShoppingList;
use App\Actions\Planning\RecordCartAvailability;
use App\Actions\Planning\SaveProductMatch;
use App\Enums\MealPlanStatus;
use App\Enums\WalmartAvailability;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\WalmartMatch;
use App\Models\WalmartMatchProposal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
     * matches the cart link covered (the buy list's matched products) are the
     * ones judged: flagged when a pasted line names them, ok otherwise.
     */
    public function recordAvailability(): void
    {
        if (trim($this->outOfStockPaste) === '' && trim($this->shipOnlyPaste) === '') {
            $this->addError('availability', 'Paste the unavailable list or the shipping section first.');

            return;
        }

        $carted = collect($this->shoppingList['buy_list'])->whereNotNull('product_url')->pluck('name');

        $this->availabilityReport = app(RecordCartAvailability::class)->handle(
            WalmartMatch::query()->whereHas('ingredient', fn ($query) => $query->whereIn('name', $carted))->get(),
            $this->outOfStockPaste,
            $this->shipOnlyPaste,
        );

        $this->outOfStockPaste = '';
        $this->shipOnlyPaste = '';
    }

    /**
     * Flagged matches by ingredient name: the last cart import said they are
     * not in the pickup order.
     *
     * @return Collection<string, WalmartMatch>
     */
    #[Computed]
    public function flagged(): Collection
    {
        $names = collect($this->shoppingList['lines'])->collapse()->pluck('name');

        return WalmartMatch::query()
            ->whereHas('ingredient', fn ($query) => $query->whereIn('name', $names))
            ->whereNotNull('availability')
            ->where('availability', '!=', WalmartAvailability::Ok->value)
            ->with('ingredient')
            ->get()
            ->keyBy(fn (WalmartMatch $match) => $match->ingredient->name);
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
            'lines' => $this->shoppingList['lines'],
            'cartUrl' => $this->shoppingList['cart_link'],
            'flagged' => $this->flagged,
            'proposedCount' => WalmartMatchProposal::query()
                ->whereHas('ingredient', fn ($query) => $query->whereIn('name', collect($this->shoppingList['lines'])->collapse()->pluck('name')))
                ->count(),
            'markdown' => $this->markdownExport(),
            'plain' => $this->plainExport(),
        ]);
    }
}
