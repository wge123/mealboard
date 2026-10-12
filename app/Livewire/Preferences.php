<?php

namespace App\Livewire;

use App\Exceptions\HouseholdPreferenceRefused;
use App\Support\HouseholdPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Settings section for the household preferences: the weekday limits, the
 * household size and the avoided ingredients.
 */
class Preferences extends Component
{
    /** The tiles offered for each setting; any other whole number goes through the Other tile. */
    public const array MINUTE_PRESETS = [15, 20, 30, 45, 60, 90];

    public const array INGREDIENT_PRESETS = [5, 6, 8, 10, 12, 15];

    public const array SIZE_PRESETS = [1, 2, 3, 4, 5, 6];

    /** Common dislikes offered as one-tap tiles. */
    public const array AVOID_SUGGESTIONS = ['anchovy', 'blue cheese', 'cilantro', 'coconut', 'eggplant', 'liver', 'mushroom', 'olive', 'peanut', 'pork', 'shellfish', 'tofu'];

    public int|string $weekdayMinutes = '';

    public int|string $weekdayIngredients = '';

    public int|string $householdSize = '';

    public string $newAvoided = '';

    public function mount(HouseholdPreferences $preferences): void
    {
        $limits = $preferences->weekdayLimits();

        $this->weekdayMinutes = $limits['minutes'];
        $this->weekdayIngredients = $limits['ingredients'];
        $this->householdSize = $preferences->householdSize();
    }

    public function saveLimits(HouseholdPreferences $preferences): void
    {
        $this->validate([
            'weekdayMinutes' => 'required|integer',
            'weekdayIngredients' => 'required|integer',
        ]);

        try {
            $preferences->setWeekdayLimits((int) $this->weekdayMinutes, (int) $this->weekdayIngredients);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('weekdayLimits', $e->getMessage());
        }
    }

    public function saveHouseholdSize(HouseholdPreferences $preferences): void
    {
        $this->validate(['householdSize' => 'required|integer']);

        try {
            $preferences->setHouseholdSize((int) $this->householdSize);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('householdSize', $e->getMessage());
        }
    }

    public function pickMinutes(HouseholdPreferences $preferences, int $minutes): void
    {
        $this->weekdayMinutes = $minutes;
        $this->saveLimits($preferences);
    }

    public function pickIngredients(HouseholdPreferences $preferences, int $ingredients): void
    {
        $this->weekdayIngredients = $ingredients;
        $this->saveLimits($preferences);
    }

    public function pickHouseholdSize(HouseholdPreferences $preferences, int $size): void
    {
        $this->householdSize = $size;
        $this->saveHouseholdSize($preferences);
    }

    public function toggleAvoided(HouseholdPreferences $preferences, string $word): void
    {
        if ($preferences->avoidedIngredients()->contains(mb_strtolower(trim($word)))) {
            $preferences->stopAvoiding($word);

            return;
        }

        try {
            $preferences->avoid($word);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('newAvoided', $e->getMessage());
        }
    }

    public function avoid(HouseholdPreferences $preferences): void
    {
        try {
            $preferences->avoid($this->newAvoided);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('newAvoided', $e->getMessage());

            return;
        }

        $this->reset('newAvoided');
    }

    public function stopAvoiding(HouseholdPreferences $preferences, string $word): void
    {
        $preferences->stopAvoiding($word);
    }

    public function render(HouseholdPreferences $preferences): View
    {
        $avoided = $preferences->avoidedIngredients();

        return view('livewire.preferences', [
            'avoided' => $avoided,
            // Suggestions first, then the household's own words, each once.
            'avoidTiles' => collect(self::AVOID_SUGGESTIONS)->merge($avoided)->unique()->values(),
        ]);
    }
}
