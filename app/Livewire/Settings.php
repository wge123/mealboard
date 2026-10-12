<?php

namespace App\Livewire;

use App\Models\Ingredient;
use App\Models\KitchenToolKind;
use App\Models\YouTubeChannel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The settings page: every settings section on one scrolling page beside a
 * section menu. Each section is its own component; they announce a change
 * with `settings-changed` so the menu's counts follow.
 */
#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    #[On('settings-changed')]
    public function refreshCounts(): void
    {
        // Re-rendering is the whole job: the counts are read in render().
    }

    public function render(): View
    {
        return view('livewire.settings', [
            'ownedCount' => KitchenToolKind::query()->where('owned', true)->count(),
            'stapleCount' => Ingredient::query()->where('is_pantry_staple', true)->count(),
            'activeChannelCount' => YouTubeChannel::query()->where('active', true)->count(),
        ]);
    }
}
