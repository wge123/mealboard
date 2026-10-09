<?php

namespace App\Support;

use App\Models\KitchenToolKind;
use Illuminate\Support\Collection;

/**
 * The household's kitchen tool inventory: one home for every question about
 * kitchen tool kinds. Words are matched trimmed and lowercased. Later tickets
 * add resolve, addKind, setOwned, setNote, deleteKind and the other-name writes
 * here.
 */
class KitchenToolInventory
{
    /**
     * Every kind's name, catalog plus household-added.
     *
     * @return Collection<int, string>
     */
    public function kinds(): Collection
    {
        return KitchenToolKind::query()->orderBy('name')->pluck('name');
    }

    /**
     * Whether the household owns the kind with this name. An unknown name is not owned.
     */
    public function owns(string $kind): bool
    {
        return KitchenToolKind::query()
            ->where('name', $this->normalize($kind))
            ->where('owned', true)
            ->exists();
    }

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
