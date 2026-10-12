<?php

namespace App\Livewire;

use App\Enums\KitchenToolFilter;
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

    public KitchenToolFilter $filter = KitchenToolFilter::All;

    /** The kind whose details sheet is open, if any. */
    public ?int $detailId = null;

    public function toggleOwned(KitchenToolInventory $inventory, int $kindId): void
    {
        $kind = KitchenToolKind::query()->findOrFail($kindId);

        $inventory->setOwned($kind, ! $kind->owned);
        $this->dispatch(Settings::CHANGED);
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

        $this->dispatch(Settings::CHANGED);
    }

    public function openDetails(int $kindId): void
    {
        $this->detailId = KitchenToolKind::query()->findOrFail($kindId)->id;
        $this->reset('newOtherName');
        $this->resetErrorBag('addOtherName');
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
        $this->dispatch(Settings::CHANGED);
    }

    public function deleteKind(KitchenToolInventory $inventory, int $kindId): void
    {
        try {
            $inventory->deleteKind(KitchenToolKind::query()->findOrFail($kindId));
            unset($this->notes[$kindId]);
            $this->detailId = null;
            $this->dispatch(Settings::CHANGED);
        } catch (KitchenToolRefused $e) {
            $this->addError('deleteKind', $e->getMessage());
        }
    }

    public string $newOtherName = '';

    public function addOtherName(KitchenToolInventory $inventory, int $kindId): void
    {
        try {
            $inventory->addOtherName(KitchenToolKind::query()->findOrFail($kindId), $this->newOtherName);
        } catch (KitchenToolRefused $e) {
            $this->addError('addOtherName', $e->getMessage());

            return;
        }

        $this->reset('newOtherName');
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
            ->when($this->filter !== KitchenToolFilter::All, fn ($q) => $q->where('owned', $this->filter === KitchenToolFilter::Owned))
            ->orderByDesc('owned')
            ->orderBy('name')
            ->get();

        // The open kind's other names: household ones can be removed, catalog ones are only listed.
        $detail = $this->detailId === null ? null : KitchenToolKind::query()
            ->with(['otherNames' => fn ($q) => $q->orderBy('name')])
            ->find($this->detailId);

        return view('livewire.kitchen-tools', ['kinds' => $kinds, 'detail' => $detail]);
    }
}
