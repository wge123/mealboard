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
    /**
     * Note field values, keyed by kind name.
     *
     * @var array<string, string>
     */
    public array $notes = [];

    public function mount(): void
    {
        $this->notes = KitchenToolKind::query()
            ->whereNotNull('note')
            ->pluck('note', 'name')
            ->all();
    }

    public function toggleOwned(KitchenToolInventory $inventory, string $kind): void
    {
        $inventory->setOwned($kind, ! $inventory->owns($kind));
    }

    public function saveNote(KitchenToolInventory $inventory, string $kind): void
    {
        $inventory->setNote($kind, $this->notes[$kind] ?? null);

        $this->notes[$kind] = KitchenToolKind::query()->where('name', $kind)->value('note') ?? '';
    }

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
