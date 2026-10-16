<?php

namespace App\Livewire;

use App\Enums\LibraryShelving;
use App\Enums\MealType;
use App\Enums\RecipeStatus;
use App\Models\MealLog;
use App\Models\Recipe;
use App\Support\MissingKitchenTools;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The recipe library. With no search and no chip on, the approved recipes sit
 * on shelves grouped one way; any search or chip turns the page into a wall
 * of the matching tiles. The household's library is small, so every approved
 * recipe is loaded once and filtered and grouped in memory.
 */
#[Layout('layouts.app', ['width' => 'wide'])]
#[Title('Recipes')]
class RecipeLibrary extends Component
{
    /** The chip facets: a recipe matches a facet when it has any of the chosen values. */
    private const array FACETS = ['mealTypes', 'cuisines', 'tags'];

    public string $search = '';

    /** @var list<string> */
    public array $mealTypes = [];

    /** @var list<string> */
    public array $cuisines = [];

    /** @var list<string> */
    public array $tags = [];

    public ?int $maxMinutes = null;

    public ?int $minRating = null;

    public LibraryShelving $groupBy = LibraryShelving::Meal;

    /**
     * Turn a chip on or off.
     */
    public function toggle(string $facet, string $value): void
    {
        abort_unless(in_array($facet, self::FACETS, true), 422);

        $this->{$facet} = in_array($value, $this->{$facet}, true)
            ? array_values(array_diff($this->{$facet}, [$value]))
            : [...$this->{$facet}, $value];
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'mealTypes', 'cuisines', 'tags', 'maxMinutes', 'minRating');
    }

    public function render(): View
    {
        $all = Recipe::query()->where('status', RecipeStatus::Approved)->orderBy('title')->get();

        // Average rating per recipe id, from meal_logs through planned_meals.
        $ratings = MealLog::query()
            ->join('planned_meals', 'planned_meals.id', '=', 'meal_logs.planned_meal_id')
            ->whereNotNull('meal_logs.rating')
            ->groupBy('planned_meals.recipe_id')
            ->selectRaw('planned_meals.recipe_id as recipe_id, AVG(meal_logs.rating) as avg_rating')
            ->pluck('avg_rating', 'recipe_id')
            ->map(fn ($avg) => (float) $avg);

        $recipes = $all->filter(fn (Recipe $recipe) => $this->matches($recipe, $ratings))->values();
        $filtering = $this->search !== '' || $this->mealTypes !== [] || $this->cuisines !== [] || $this->tags !== []
            || $this->maxMinutes !== null || $this->minRating !== null;

        return view('livewire.recipe-library', [
            'recipes' => $recipes,
            'filtering' => $filtering,
            'shelves' => $filtering ? collect() : $this->shelves($recipes, $ratings),
            'ratings' => $ratings,
            'missingTools' => app(MissingKitchenTools::class)->forMany($recipes),
            // Each meal chip counts what the other filters leave, so it says what tapping it would show.
            'mealTypeCounts' => collect(MealType::cases())->mapWithKeys(fn (MealType $type) => [
                $type->value => $all->filter(fn (Recipe $recipe) => $recipe->meal_type === $type && $this->matches($recipe, $ratings, except: 'mealTypes'))->count(),
            ]),
            'cuisineOptions' => $all->pluck('cuisine')->filter()->countBy()->sortKeys()->sortDesc()->keys(),
            // Tags on one recipe only would crowd the chip row; a search still finds them.
            'tagOptions' => $all->pluck('tags')->flatten()->filter()->countBy()->filter(fn (int $count) => $count > 1)->sortKeys()->sortDesc()->keys(),
        ]);
    }

    /**
     * @param  Collection<int, float>  $ratings
     */
    private function matches(Recipe $recipe, Collection $ratings, ?string $except = null): bool
    {
        $minutes = self::minutes($recipe);
        $tags = $recipe->tags ?? [];
        $search = mb_strtolower($this->search);

        return ($search === '' || Str::contains(mb_strtolower($recipe->title.' '.$recipe->description.' '.implode(' ', $tags)), $search))
            && ($except === 'mealTypes' || $this->mealTypes === [] || in_array($recipe->meal_type->value, $this->mealTypes, true))
            && ($this->cuisines === [] || in_array($recipe->cuisine, $this->cuisines, true))
            && ($this->tags === [] || array_intersect($this->tags, $tags) !== [])
            // A recipe with no time recorded never passes a time limit.
            && ($this->maxMinutes === null || ($minutes > 0 && $minutes <= $this->maxMinutes))
            // Unrated recipes are left out once a rating filter is on.
            && ($this->minRating === null || $ratings->get($recipe->id, 0) >= $this->minRating);
    }

    /**
     * The shelves for the current grouping, in reading order, each
     * {title, key, recipes}. Empty shelves are left out.
     *
     * @param  Collection<int, Recipe>  $recipes
     * @param  Collection<int, float>  $ratings
     * @return Collection<int, array{title: string, key: string, recipes: Collection<int, Recipe>}>
     */
    private function shelves(Collection $recipes, Collection $ratings): Collection
    {
        [$shelfOf, $order] = match ($this->groupBy) {
            LibraryShelving::Meal => [
                fn (Recipe $recipe) => $recipe->meal_type->value,
                array_map(fn (MealType $type) => $type->value, MealType::cases()),
            ],
            LibraryShelving::Cuisine => [
                fn (Recipe $recipe) => $recipe->cuisine ?? 'other',
                // Biggest shelf first, ties alphabetical; recipes with no cuisine last.
                $recipes->pluck('cuisine')->filter()->countBy()->sortKeys()->sortDesc()->keys()->push('other')->all(),
            ],
            LibraryShelving::Time => [
                fn (Recipe $recipe) => match (true) {
                    self::minutes($recipe) === 0 => 'time unknown',
                    self::minutes($recipe) <= 20 => 'quick',
                    self::minutes($recipe) <= 40 => 'weeknight',
                    default => 'takes a while',
                },
                ['quick', 'weeknight', 'takes a while', 'time unknown'],
            ],
            LibraryShelving::Rating => [
                fn (Recipe $recipe) => match (true) {
                    ! $ratings->has($recipe->id) => 'not rated yet',
                    $ratings[$recipe->id] >= 4 => 'loved',
                    default => 'fine',
                },
                ['loved', 'fine', 'not rated yet'],
            ],
        };

        $titles = ['quick' => 'Quick (20 min or less)', 'weeknight' => 'Weeknight (21–40 min)', 'takes a while' => 'Takes a while (over 40 min)', 'loved' => 'Loved (4★ and up)', 'any' => 'Any meal'];
        $grouped = $recipes->groupBy($shelfOf);

        return collect($order)
            ->filter(fn (string $key) => $grouped->has($key))
            ->map(fn (string $key) => [
                'key' => Str::slug($key),
                'title' => $titles[$key] ?? Str::ucfirst($key),
                'recipes' => $grouped[$key],
            ])
            ->values();
    }

    public static function minutes(Recipe $recipe): int
    {
        return (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
    }
}
