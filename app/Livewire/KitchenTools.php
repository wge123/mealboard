<?php

namespace App\Livewire;

use App\Enums\KitchenToolOrigin;
use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
use App\Support\KitchenToolInventory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kitchen tools')]
class KitchenTools extends Component
{
    /**
     * Note field values, keyed by kind id (a name can contain a dot, which
     * would break the wire:model path).
     *
     * @var array<int, string>
     */
    public array $notes = [];

    public function mount(): void
    {
        $this->notes = KitchenToolKind::query()
            ->whereNotNull('note')
            ->pluck('note', 'id')
            ->all();
    }

    public function toggleOwned(KitchenToolInventory $inventory, int $kindId): void
    {
        $kind = KitchenToolKind::query()->findOrFail($kindId);

        $inventory->setOwned($kind, ! $kind->owned);
    }

    public function saveNote(KitchenToolInventory $inventory, int $kindId): void
    {
        $kind = KitchenToolKind::query()->findOrFail($kindId);

        $inventory->setNote($kind, $this->notes[$kindId] ?? null);

        $this->notes[$kindId] = $kind->note ?? '';
    }

    public string $newKind = '';

    public function addKind(KitchenToolInventory $inventory): void
    {
        try {
            $kind = $inventory->addKind($this->newKind);
        } catch (KitchenToolRefused $e) {
            $this->addError('addKind', $e->getMessage());

            return;
        }

        $this->notes[$kind->id] = '';
        $this->reset('newKind');
    }

    public function deleteKind(KitchenToolInventory $inventory, int $kindId): void
    {
        try {
            $inventory->deleteKind(KitchenToolKind::query()->findOrFail($kindId));
            unset($this->notes[$kindId]);
        } catch (KitchenToolRefused $e) {
            $this->addError('deleteKind', $e->getMessage());
        }
    }

    public function removeOtherName(KitchenToolInventory $inventory, int $otherNameId): void
    {
        try {
            $inventory->removeOtherName(KitchenToolOtherName::query()->findOrFail($otherNameId));
        } catch (KitchenToolRefused $e) {
            $this->addError('removeOtherName', $e->getMessage());
        }
    }

    public function render(): View
    {
        // Owned kinds first, then the rest, each group alphabetical.
        $kinds = KitchenToolKind::query()
            ->with(['otherNames' => fn ($q) => $q->where('origin', KitchenToolOrigin::Household)->orderBy('name')])
            ->orderByDesc('owned')
            ->orderBy('name')
            ->get();

        return view('livewire.kitchen-tools', ['kinds' => $kinds]);
    }
}
