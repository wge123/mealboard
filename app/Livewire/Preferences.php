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

    public const array QUICK_NIGHT_PRESETS = [10, 15, 20, 25, 30];

    public const array INGREDIENT_PRESETS = [5, 6, 8, 10, 12, 15];

    public const array SIZE_PRESETS = [1, 2, 3, 4, 5, 6];

    /** Common dislikes offered as one-tap tiles. */
    public const array AVOID_SUGGESTIONS = ['anchovy', 'blue cheese', 'cilantro', 'coconut', 'eggplant', 'liver', 'mushroom', 'olive', 'peanut', 'pork', 'shellfish', 'tofu'];

    public int|string $weekdayMinutes = '';

    public int|string $weekdayIngredients = '';

    public int|string $householdSize = '';

    public int|string $quickNightMinutes = '';

    public string $newAvoided = '';

    public function mount(HouseholdPreferences $preferences): void
    {
        $limits = $preferences->weekdayLimits();

        $this->weekdayMinutes = $limits['minutes'];
        $this->weekdayIngredients = $limits['ingredients'];
        $this->quickNightMinutes = $preferences->quickNightLimit();
        $this->householdSize = $preferences->householdSize();
    }

    /**
     * Save the minutes limit alone; the ingredient limit is re-saved from
     * storage, never from its field, so an unsent draft there stays unsent.
     */
    public function saveMinutes(HouseholdPreferences $preferences): void
    {
        $this->validate(['weekdayMinutes' => 'required|integer']);

        try {
            $preferences->setWeekdayLimits((int) $this->weekdayMinutes, $preferences->weekdayLimits()['ingredients']);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('weekdayMinutes', $e->getMessage());
        }
    }

    /**
     * Save the ingredient limit alone; the minutes limit is re-saved from storage.
     */
    public function saveIngredients(HouseholdPreferences $preferences): void
    {
        $this->validate(['weekdayIngredients' => 'required|integer']);

        try {
            $preferences->setWeekdayLimits($preferences->weekdayLimits()['minutes'], (int) $this->weekdayIngredients);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('weekdayIngredients', $e->getMessage());
        }
    }

    public function saveQuickNight(HouseholdPreferences $preferences): void
    {
        $this->validate(['quickNightMinutes' => 'required|integer']);

        try {
            $preferences->setQuickNightLimit((int) $this->quickNightMinutes);
        } catch (HouseholdPreferenceRefused $e) {
            $this->addError('quickNightMinutes', $e->getMessage());
        }
    }

    public function pickQuickNight(HouseholdPreferences $preferences, int $minutes): void
    {
        $this->quickNightMinutes = $minutes;
        $this->saveQuickNight($preferences);
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
        $this->saveMinutes($preferences);
    }

    public function pickIngredients(HouseholdPreferences $preferences, int $ingredients): void
    {
        $this->weekdayIngredients = $ingredients;
        $this->saveIngredients($preferences);
    }

    public function pickHouseholdSize(HouseholdPreferences $preferences, int $size): void
    {
        $this->householdSize = $size;
        $this->saveHouseholdSize($preferences);
    }

    public function toggleAvoided(HouseholdPreferences $preferences, string $word): void
    {
        if ($preferences->avoids($word)) {
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

        // Tiles mark what is stored, not what a field holds after a refused save.
        return view('livewire.preferences', [
            'stored' => [...$preferences->weekdayLimits(), 'quickNight' => $preferences->quickNightLimit(), 'size' => $preferences->householdSize()],
            'avoided' => $avoided,
            // Suggestions first, then the household's own words, each once.
            'avoidTiles' => collect(self::AVOID_SUGGESTIONS)->merge($avoided)->unique()->values(),
        ]);
    }
}
