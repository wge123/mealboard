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
 * Approval cards page (step 20): one pending recipe at a time, oldest first.
 * Approve stamps attribution via SetRecipeStatus; reject KEEPS the row as
 * status=rejected so discovery can dedupe against it.
 */
#[Layout('layouts.app')]
#[Title('Approve recipes')]
class RecipeApproval extends Component
{
    /** Cards decided this session — drives the "N of M pending" progress line. */
    public int $reviewed = 0;

    public function approve(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Approved);
    }

    public function reject(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Rejected);
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
            $this->reviewed++;
        }
    }

    public function render(): View
    {
        $recipe = Recipe::query()
            ->with(['ingredients', 'recipeRequest'])
            ->where('status', RecipeStatus::Pending)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $missing = $recipe === null ? [] : app(MissingKitchenTools::class)->for($recipe);

        return view('livewire.recipe-approval', [
            'recipe' => $recipe,
            'missing' => $missing,
            // Owned kinds only: naming a word as one of them always clears the flag.
            'kinds' => $missing === [] ? collect() : KitchenToolKind::query()->where('owned', true)->orderBy('name')->get(),
            'pending' => Recipe::query()->where('status', RecipeStatus::Pending)->count(),
        ]);
    }
}
