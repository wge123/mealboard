<?php

namespace App\Livewire;

use App\Enums\KitchenToolOrigin;
use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
use App\Support\KitchenToolInventory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

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

    /** Which tiles show: all, owned or missing. */
    public string $filter = 'all';

    /** The kind whose details sheet is open, if any. */
    public ?int $detailId = null;

    public function toggleOwned(KitchenToolInventory $inventory, int $kindId): void
    {
        $kind = KitchenToolKind::query()->findOrFail($kindId);

        $inventory->setOwned($kind, ! $kind->owned);
        $this->dispatch('settings-changed');
    }

    /**
     * Mark the tiles picked in select mode owned or not owned.
     *
     * @param  list<int>  $kindIds
     */
    public function markMany(KitchenToolInventory $inventory, array $kindIds, bool $owned): void
    {
        try {
            $inventory->setOwnedMany($kindIds, $owned);
        } catch (KitchenToolRefused $e) {
            $this->addError('markMany', $e->getMessage());

            return;
        }

        $this->dispatch('settings-changed');
    }

    public function openDetails(int $kindId): void
    {
        $this->detailId = KitchenToolKind::query()->findOrFail($kindId)->id;
    }

    public function closeDetails(): void
    {
        $this->detailId = null;
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
        $this->dispatch('settings-changed');
    }

    public function deleteKind(KitchenToolInventory $inventory, int $kindId): void
    {
        try {
            $inventory->deleteKind(KitchenToolKind::query()->findOrFail($kindId));
            unset($this->notes[$kindId]);
            $this->detailId = null;
            $this->dispatch('settings-changed');
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
            ->when($this->filter !== 'all', fn ($q) => $q->where('owned', $this->filter === 'owned'))
            ->orderByDesc('owned')
            ->orderBy('name')
            ->get();

        // Only the open kind's household other names are shown; catalog ones work but are not listed.
        $detail = $this->detailId === null ? null : KitchenToolKind::query()
            ->with(['otherNames' => fn ($q) => $q->where('origin', KitchenToolOrigin::Household)->orderBy('name')])
            ->find($this->detailId);

        return view('livewire.kitchen-tools', ['kinds' => $kinds, 'detail' => $detail]);
    }
}
