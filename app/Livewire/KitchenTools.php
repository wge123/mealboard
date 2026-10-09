<?php

namespace App\Livewire;

use App\Models\KitchenToolKind;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kitchen tools')]
class KitchenTools extends Component
{
    public function render(): View
    {
        // Owned kinds first, then the rest, each group alphabetical.
        $kinds = KitchenToolKind::query()
            ->orderByDesc('owned')
            ->orderBy('name')
            ->get();

        return view('livewire.kitchen-tools', ['kinds' => $kinds]);
    }
}
