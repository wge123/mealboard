<?php

namespace App\Support;

use App\Enums\DiscoveryLane;
use App\Enums\IngredientCategory;
use App\Exceptions\HouseholdPreferenceRefused;
use App\Models\AvoidedIngredient;
use App\Models\HouseholdPreference;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The household preferences: the weekday limits, the household size, the
 * avoided ingredients and the pantry staples, plus the one check that drops a
 * discovered candidate the household would not want. Words are matched
 * trimmed and lowercased; an avoided word matches an ingredient name as a
 * whole word ("cilantro" catches "fresh cilantro", not "cilantrolike").
 */
class HouseholdPreferences
{
    /**
     * The most total minutes and the most non-staple ingredients a recipe
     * found by daily discovery may have.
     *
     * @return array{minutes: int, ingredients: int}
     */
    public function weekdayLimits(): array
    {
        $row = $this->row();

        return ['minutes' => $row->weekday_minutes_limit, 'ingredients' => $row->weekday_ingredient_limit];
    }

    /**
     * @throws HouseholdPreferenceRefused
     */
    public function setWeekdayLimits(int $minutes, int $ingredients): void
    {
        $this->row()->update([
            'weekday_minutes_limit' => $this->atLeastOne($minutes, 'The time limit'),
            'weekday_ingredient_limit' => $this->atLeastOne($ingredients, 'The ingredient limit'),
        ]);
    }

    public function householdSize(): int
    {
        return $this->row()->household_size;
    }

    /**
     * @throws HouseholdPreferenceRefused
     */
    public function setHouseholdSize(int $size): void
    {
        $this->row()->update(['household_size' => $this->atLeastOne($size, 'The household size')]);
    }

    /**
     * The avoided words, alphabetical.
     *
     * @return Collection<int, string>
     */
    public function avoidedIngredients(): Collection
    {
        return AvoidedIngredient::query()->orderBy('word')->pluck('word');
    }

    /**
     * Add an avoided word. Refuses a blank word and one already avoided.
     *
     * @throws HouseholdPreferenceRefused
     */
    public function avoid(string $word): void
    {
        $word = $this->normalize($word);

        if ($word === '') {
            throw new HouseholdPreferenceRefused('Enter an ingredient to avoid.');
        }

        if (AvoidedIngredient::query()->where('word', $word)->exists()) {
            throw new HouseholdPreferenceRefused("\"{$word}\" is already avoided.");
        }

        AvoidedIngredient::create(['word' => $word]);
    }

    public function stopAvoiding(string $word): void
    {
        AvoidedIngredient::query()->where('word', $this->normalize($word))->delete();
    }

    /**
     * The names of the ingredients the household keeps in stock, alphabetical.
     *
     * @return Collection<int, string>
     */
    public function staples(): Collection
    {
        return Ingredient::query()->where('is_pantry_staple', true)->orderBy('name')->pluck('name');
    }

    /**
     * Mark an ingredient as a staple or not, finding or creating it by its
     * lowercased name the way recipe ingredients are matched.
     *
     * @throws HouseholdPreferenceRefused
     */
    public function setStaple(string $name, bool $staple): Ingredient
    {
        $name = $this->normalize($name);

        if ($name === '') {
            throw new HouseholdPreferenceRefused('Enter an ingredient name.');
        }

        $ingredient = Ingredient::query()->firstOrCreate(
            ['name' => $name],
            ['category' => IngredientCategory::Other, 'is_pantry_staple' => false],
        );

        $ingredient->update(['is_pantry_staple' => $staple]);

        return $ingredient;
    }

    /**
     * The prompt sentence naming the pantry staples, which do not count toward
     * the ingredient limit; says so when there are none yet.
     */
    public function staplesPromptLine(): string
    {
        $staples = $this->staples();

        return $staples->isNotEmpty()
            ? "Pantry staples the household keeps in stock, which do not count toward the ingredient limit: {$staples->implode(', ')}."
            : 'The household has marked no pantry staples yet, so every ingredient counts toward the limit.';
    }

    /**
     * The prompt sentence for the avoided ingredients; empty when none.
     */
    public function avoidedPromptLine(): string
    {
        $avoided = $this->avoidedIngredients();

        return $avoided->isNotEmpty()
            ? "Never use these ingredients (a household rule, no exceptions): {$avoided->implode(', ')}."
            : '';
    }

    /**
     * Why a shaped candidate is refused on this lane; empty when it passes.
     * Avoided ingredients refuse on both lanes. The weekday limits refuse on
     * the scheduled lane only: a total time of 0 is unknown and passes, and
     * pantry staples do not count toward the ingredient limit.
     *
     * @param  array<string, mixed>  $candidate
     * @return list<string>
     */
    public function refusals(array $candidate, DiscoveryLane $lane): array
    {
        $names = array_column($candidate['ingredients'], 'name');
        $reasons = [];

        $avoided = $this->avoidedIn($names, $this->avoidedIngredients());

        if ($avoided !== []) {
            $reasons[] = 'contains avoided ingredient: '.implode(', ', $avoided);
        }

        if ($lane === DiscoveryLane::Request) {
            return $reasons;
        }

        $limits = $this->weekdayLimits();
        $minutes = (int) $candidate['prep_minutes'] + (int) $candidate['cook_minutes'];

        if ($minutes > $limits['minutes']) {
            $reasons[] = "{$minutes} minutes total is over the weekday limit of {$limits['minutes']}";
        }

        $staples = $this->staples()->flip();
        $counted = collect($names)
            ->map(fn (string $name) => $this->normalize($name))
            ->reject(fn (string $name) => $staples->has($name))
            ->unique()
            ->count();

        if ($counted > $limits['ingredients']) {
            $reasons[] = "{$counted} ingredients beyond the pantry staples is over the weekday limit of {$limits['ingredients']}";
        }

        return $reasons;
    }

    /**
     * The avoided words each recipe's ingredients contain, keyed by recipe id,
     * in one query for the words; every given recipe has a key (empty when it
     * contains none). Ingredients are loaded when the recipe has not.
     *
     * @param  iterable<Recipe>  $recipes
     * @return array<int, list<string>>
     */
    public function avoidedInRecipes(iterable $recipes): array
    {
        $recipes = EloquentCollection::make(collect($recipes)->all());
        $avoided = $this->avoidedIngredients();
        $result = [];

        if ($avoided->isEmpty()) {
            return array_fill_keys($recipes->pluck('id')->all(), []);
        }

        $recipes->loadMissing('ingredients');

        foreach ($recipes as $recipe) {
            $result[$recipe->id] = $this->avoidedIn($recipe->ingredients->pluck('name')->all(), $avoided);
        }

        return $result;
    }

    /**
     * The avoided words found in these ingredient names, whole-word and
     * ignoring case, each once.
     *
     * @param  list<string>  $names
     * @param  Collection<int, string>  $avoided
     * @return list<string>
     */
    private function avoidedIn(array $names, Collection $avoided): array
    {
        return $avoided
            ->filter(fn (string $word) => collect($names)->contains(
                fn (string $name) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu', $name) === 1,
            ))
            ->values()
            ->all();
    }

    private function row(): HouseholdPreference
    {
        // The migration inserts the one row; zero or two rows is a broken database.
        return HouseholdPreference::query()->sole();
    }

    /**
     * @throws HouseholdPreferenceRefused
     */
    private function atLeastOne(int $value, string $label): int
    {
        if ($value < 1) {
            throw new HouseholdPreferenceRefused("{$label} must be a whole number of 1 or more.");
        }

        return $value;
    }

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
