<?php

namespace App\Livewire;

use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Support\KitchenToolInventory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kitchen tools')]
class KitchenTools extends Component
{
    public string $newKind = '';

    public function addKind(KitchenToolInventory $inventory): void
    {
        try {
            $inventory->addKind($this->newKind);
        } catch (KitchenToolRefused $e) {
            $this->addError('newKind', $e->getMessage());

            return;
        }

        $this->reset('newKind');
    }

    public function deleteKind(KitchenToolInventory $inventory, int $kindId): void
    {
        try {
            $inventory->deleteKind(KitchenToolKind::query()->findOrFail($kindId));
        } catch (KitchenToolRefused $e) {
            $this->addError('delete', $e->getMessage());
        }
    }

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
