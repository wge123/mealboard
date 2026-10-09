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

    /**
     * Mark a kind owned or not owned.
     *
     * @throws \InvalidArgumentException when the kind is unknown
     */
    public function setOwned(string $kind, bool $owned): void
    {
        $this->findKind($kind)->update(['owned' => $owned]);
    }

    /**
     * Set or clear a kind's note. A note is for people only and never takes
     * part in matching; an empty or blank note is stored as no note.
     *
     * @throws \InvalidArgumentException when the kind is unknown
     */
    public function setNote(string $kind, ?string $note): void
    {
        $note = $note === null ? '' : trim($note);

        $this->findKind($kind)->update(['note' => $note === '' ? null : $note]);
    }

    private function findKind(string $kind): KitchenToolKind
    {
        return KitchenToolKind::query()->where('name', $this->normalize($kind))->first()
            ?? throw new \InvalidArgumentException("Unknown kitchen tool kind: {$kind}");
    }

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
