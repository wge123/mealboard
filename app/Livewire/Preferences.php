<?php

namespace App\Livewire;

use App\Exceptions\HouseholdPreferenceRefused;
use App\Support\HouseholdPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings section for the household preferences: the weekday limits, the
 * household size and the avoided ingredients.
 */
#[Layout('layouts.app')]
#[Title('Preferences')]
class Preferences extends Component
{
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
            'weekdayMinutes' => 'required|integer|min:1',
            'weekdayIngredients' => 'required|integer|min:1',
        ]);

        $preferences->setWeekdayLimits((int) $this->weekdayMinutes, (int) $this->weekdayIngredients);
    }

    public function saveHouseholdSize(HouseholdPreferences $preferences): void
    {
        $this->validate(['householdSize' => 'required|integer|min:1']);

        $preferences->setHouseholdSize((int) $this->householdSize);
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
        return view('livewire.preferences', [
            'avoided' => $preferences->avoidedIngredients(),
        ]);
    }
}
