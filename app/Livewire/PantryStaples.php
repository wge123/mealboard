<?php

namespace App\Livewire;

use App\Enums\PantryFilter;
use App\Exceptions\HouseholdPreferenceRefused;
use App\Models\Ingredient;
use App\Support\HouseholdPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Settings section for the pantry staples: which ingredients the household
 * keeps in stock, so the buy list leaves them out.
 */
class PantryStaples extends Component
{
    /** Rows shown at once; the search narrows the rest. */
    private const int LIMIT = 60;

    public string $search = '';

    public string $newStaple = '';

    public PantryFilter $filter = PantryFilter::All;

    public function toggle(HouseholdPreferences $preferences, int $ingredientId): void
    {
        $ingredient = Ingredient::query()->findOrFail($ingredientId);

        $preferences->setStaple($ingredient->name, ! $ingredient->is_pantry_staple);
        $this->dispatch(Settings::CHANGED);
    }

    /**
     * Mark the tiles picked in select mode as staples or not.
     *
     * @param  list<int>  $ingredientIds
     */
    public function markMany(HouseholdPreferences $preferences, array $ingredientIds, bool $staple): void
    {
        try {
            $preferences->setStaplesMany($ingredientIds, $staple);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('markMany', $e->getMessage());

            return;
        }

        $this->dispatch(Settings::CHANGED);
    }

    public function addStaple(HouseholdPreferences $preferences): void
    {
        try {
            $preferences->setStaple($this->newStaple, true);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('newStaple', $e->getMessage());

            return;
        }

        $this->reset('newStaple');
        $this->dispatch(Settings::CHANGED);
    }

    public function render(): View
    {
        $search = trim($this->search);
        $matching = fn () => Ingredient::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));

        // Every staple is listed; only the other ingredients are capped, the
        // ones most recipes use first. Until recipe import cleans ingredient
        // names, names that start with a quantity ("200g tomato") are left out
        // unless searched for.
        $staples = $this->filter === PantryFilter::Others ? collect() : $matching()->where('is_pantry_staple', true)->orderBy('name')->get();
        $others = $this->filter === PantryFilter::Staples ? collect() : $matching()
            ->where('is_pantry_staple', false)
            ->when($search === '', fn ($query) => $query->whereRaw("substr(name, 1, 1) not between '0' and '9'"))
            ->withCount('recipes')
            ->orderByDesc('recipes_count')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return view('livewire.pantry-staples', [
            'ingredients' => $staples->concat($others),
            'truncated' => $others->count() === self::LIMIT,
            'limit' => self::LIMIT,
        ]);
    }
}
