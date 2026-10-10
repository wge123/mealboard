<?php

namespace App\Livewire;

use App\Exceptions\HouseholdPreferenceRefused;
use App\Models\Ingredient;
use App\Support\HouseholdPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings section for the pantry staples: which ingredients the household
 * keeps in stock, so the buy list leaves them out.
 */
#[Layout('layouts.app')]
#[Title('Pantry staples')]
class PantryStaples extends Component
{
    /** Rows shown at once; the search narrows the rest. */
    private const int LIMIT = 60;

    public string $search = '';

    public string $newStaple = '';

    public function toggle(HouseholdPreferences $preferences, int $ingredientId): void
    {
        $ingredient = Ingredient::query()->findOrFail($ingredientId);

        $preferences->setStaple($ingredient->name, ! $ingredient->is_pantry_staple);
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
    }

    public function render(): View
    {
        $matching = fn () => Ingredient::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name');

        // Every staple is listed; only the other ingredients are capped.
        $staples = $matching()->where('is_pantry_staple', true)->get();
        $others = $matching()->where('is_pantry_staple', false)->limit(self::LIMIT)->get();

        return view('livewire.pantry-staples', [
            'ingredients' => $staples->concat($others),
            'truncated' => $others->count() === self::LIMIT,
        ]);
    }
}
