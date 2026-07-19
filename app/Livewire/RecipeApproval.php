<?php

namespace App\Livewire;

use App\Actions\Recipes\SetRecipeStatus;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
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
    public function approve(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Approved);
    }

    public function reject(int $recipeId): void
    {
        $this->decide($recipeId, RecipeStatus::Rejected);
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
        }
    }

    public function render(): View
    {
        return view('livewire.recipe-approval', [
            'recipe' => Recipe::query()
                ->with('ingredients')
                ->where('status', RecipeStatus::Pending)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first(),
        ]);
    }
}
