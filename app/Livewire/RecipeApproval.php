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
        return view('livewire.recipe-approval', [
            'recipe' => Recipe::query()
                ->with(['ingredients', 'recipeRequest'])
                ->where('status', RecipeStatus::Pending)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first(),
            'pending' => Recipe::query()->where('status', RecipeStatus::Pending)->count(),
        ]);
    }
}
