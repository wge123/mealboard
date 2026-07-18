<?php

namespace App\Livewire;

use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Recipes')]
class RecipeLibrary extends Component
{
    public string $search = '';

    public string $mealType = '';

    public string $cuisine = '';

    public string $tag = '';

    public string $minRating = '';

    public function render(): View
    {
        $approved = Recipe::query()->where('status', RecipeStatus::Approved);

        $query = (clone $approved)->orderBy('title');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->mealType !== '') {
            $query->where('meal_type', $this->mealType);
        }

        if ($this->cuisine !== '') {
            $query->where('cuisine', $this->cuisine);
        }

        if ($this->tag !== '') {
            $query->whereJsonContains('tags', $this->tag);
        }

        if ($this->minRating !== '') {
            // Average rating flows from meal_logs through planned_meals. When
            // this filter is set, unrated recipes are excluded by design.
            // The threshold is inlined (int-cast, injection-safe) because PDO
            // binds numbers as TEXT and SQLite's affinity rules make
            // `REAL >= TEXT` always false.
            $query->whereIn('recipes.id', MealLog::query()
                ->join('planned_meals', 'planned_meals.id', '=', 'meal_logs.planned_meal_id')
                ->whereNotNull('meal_logs.rating')
                ->groupBy('planned_meals.recipe_id')
                ->havingRaw('AVG(meal_logs.rating) >= '.(int) $this->minRating)
                ->select('planned_meals.recipe_id'));
        }

        return view('livewire.recipe-library', [
            'recipes' => $query->get(),
            'mealTypes' => MealType::cases(),
            'cuisines' => (clone $approved)->whereNotNull('cuisine')->distinct()->orderBy('cuisine')->pluck('cuisine'),
            'tags' => (clone $approved)->pluck('tags')->flatten()->unique()->sort()->values(),
        ]);
    }
}
