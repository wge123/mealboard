<?php

namespace App\Livewire;

use App\Actions\Recipes\SetRecipeStatus;
use App\Enums\RecipeStatus;
use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Support\KitchenToolInventory;
use App\Support\MissingKitchenTools;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Approval deck: the pending recipes as a stack of cards, oldest on top.
 * Approve stamps attribution via SetRecipeStatus; reject KEEPS the row as
 * status=rejected so discovery can dedupe against it. Skip sends a card to
 * the back of the deck for this visit only, and undo walks this visit's
 * decisions and skips back, newest first.
 */
#[Layout('layouts.app')]
#[Title('Approve recipes')]
class RecipeApproval extends Component
{
    /** Ids skipped this visit, in the order they went to the back of the deck. */
    public array $skipped = [];

    /**
     * This visit's steps, oldest first, for undo: a decision's recipe id and
     * status, or a skip's recipe id with the skipped list from before it.
     *
     * @var list<array{id: int, status: string, skipped?: list<int>}>
     */
    public array $history = [];

    public function approve(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Approved);
    }

    public function reject(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Rejected);
    }

    /** Send a pending card to the back of the deck; it stays pending. */
    public function skip(int $recipeId): void
    {
        if (! Recipe::query()->where('status', RecipeStatus::Pending)->whereKey($recipeId)->exists()) {
            return;
        }

        $this->history[] = ['id' => $recipeId, 'status' => 'skipped', 'skipped' => $this->skipped];
        $this->skipped = [...array_values(array_diff($this->skipped, [$recipeId])), $recipeId];
    }

    /**
     * Take back the newest step. A decision returns its recipe to pending,
     * unless the recipe changed elsewhere since; a skip puts the deck back.
     */
    public function undo(): void
    {
        $step = array_pop($this->history);

        if ($step === null) {
            return;
        }

        if ($step['status'] === 'skipped') {
            $this->skipped = $step['skipped'];

            return;
        }

        $recipe = Recipe::query()->where('status', $step['status'])->find($step['id']);

        if ($recipe !== null) {
            app(SetRecipeStatus::class)->handle($recipe, RecipeStatus::Pending, auth()->user());
        }
    }

    /** Add an unknown tool word as a new kitchen tool kind the household owns. */
    public function addAsNewTool(KitchenToolInventory $inventory, string $word): void
    {
        $this->guardTool(fn () => $inventory->addKind($word));
    }

    /** Name an unknown tool word as another name for a kind the household picked. */
    public function nameAsMyTool(KitchenToolInventory $inventory, string $word, int $kindId): void
    {
        $kind = KitchenToolKind::query()->find($kindId);

        if ($kind === null) {
            $this->addError('tool', 'Pick which kitchen tool it is.');

            return;
        }

        $this->guardTool(fn () => $inventory->addOtherName($kind, $word));
    }

    /** Mark a known kind owned. */
    public function markToolOwned(KitchenToolInventory $inventory, int $kindId): void
    {
        $inventory->setOwned(KitchenToolKind::query()->findOrFail($kindId), true);
    }

    private function guardTool(callable $write): void
    {
        $this->resetErrorBag('tool');

        try {
            $write();
        } catch (KitchenToolRefused $e) {
            $this->addError('tool', $e->getMessage());
        }
    }

    /**
     * The id comes from the rendered card so a stale click (card already
     * decided elsewhere) is a no-op instead of touching the wrong recipe.
     */
    private function decide(int $recipeId, RecipeStatus $status): void
    {
        $recipe = Recipe::query()
            ->where('status', RecipeStatus::Pending)
            ->find($recipeId);

        if ($recipe !== null) {
            app(SetRecipeStatus::class)->handle($recipe, $status, auth()->user());
            $this->history[] = ['id' => $recipeId, 'status' => $status->value];
            // Decided, it leaves the back of the deck; undone, it comes back on top.
            $this->skipped = array_values(array_diff($this->skipped, [$recipeId]));
        }
    }

    public function render(): View
    {
        // Oldest first, skipped cards behind the rest in the order they were skipped.
        $back = array_flip($this->skipped);
        $deck = Recipe::query()
            ->where('status', RecipeStatus::Pending)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Recipe $recipe) => $back[$recipe->id] ?? -1)
            ->values();

        $recipe = $deck->first()?->load(['ingredients', 'recipeRequest']);
        $missing = $recipe === null ? [] : app(MissingKitchenTools::class)->for($recipe);

        return view('livewire.recipe-approval', [
            'recipe' => $recipe,
            'under' => $deck->slice(1, 2)->values(),
            'missing' => $missing,
            // Owned kinds only: naming a word as one of them always clears the flag.
            'kinds' => $missing === [] ? collect() : KitchenToolKind::query()->where('owned', true)->orderBy('name')->get(),
            'pending' => $deck->count(),
        ]);
    }
}
