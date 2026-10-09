<?php

namespace App\Support;

use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * @throws KitchenToolRefused when the kind is unknown
     */
    public function setOwned(string $kind, bool $owned): void
    {
        $this->findKind($kind)->update(['owned' => $owned]);
    }

    /**
     * Set or clear a kind's note. A note is for people only and never takes
     * part in matching; an empty or blank note is stored as no note.
     *
     * @throws KitchenToolRefused when the kind is unknown
     */
    public function setNote(string $kind, ?string $note): void
    {
        $note = $note === null ? '' : trim($note);

        $this->findKind($kind)->update(['note' => $note === '' ? null : $note]);
    }

    private function findKind(string $kind): KitchenToolKind
    {
        return KitchenToolKind::query()->where('name', $this->normalize($kind))->first()
            ?? throw new KitchenToolRefused("Unknown kitchen tool kind: {$kind}");
    }

    /**
     * The kind this word names, by kind name or any other name; exact match
     * after trimming and lowercasing. Nothing otherwise (no fuzzy, plural or note matching).
     */
    public function resolve(string $word): ?KitchenToolKind
    {
        $word = $this->normalize($word);

        if ($word === '') {
            return null;
        }

        return KitchenToolKind::query()->where('name', $word)->first()
            ?? KitchenToolOtherName::query()->where('name', $word)->first()?->kind;
    }

    /**
     * Create an owned household kind. Refuses a word that already resolves.
     *
     * @throws KitchenToolRefused
     */
    public function addKind(string $name): KitchenToolKind
    {
        $name = $this->normalize($name);

        if ($name === '') {
            throw new KitchenToolRefused('Enter a name for the tool.');
        }

        if ($existing = $this->resolve($name)) {
            throw new KitchenToolRefused("\"{$name}\" is already known as ".Str::title($existing->name).'.');
        }

        return KitchenToolKind::create([
            'name' => $name,
            'origin' => KitchenToolKind::ORIGIN_HOUSEHOLD,
            'owned' => true,
        ]);
    }

    /**
     * Delete a household kind with its other names. Catalog kinds can only be marked not owned.
     *
     * @throws KitchenToolRefused
     */
    public function deleteKind(KitchenToolKind $kind): void
    {
        if ($kind->origin !== KitchenToolKind::ORIGIN_HOUSEHOLD) {
            throw new KitchenToolRefused(Str::title($kind->name).' is a catalog tool and cannot be deleted. Mark it not owned instead.');
        }

        DB::transaction(function () use ($kind) {
            $kind->otherNames()->delete();
            $kind->delete();
        });
    }

    /**
     * Add a household other name to a kind. Refuses a word that already
     * resolves to a kind name or another other name.
     *
     * @throws KitchenToolRefused
     */
    public function addOtherName(KitchenToolKind $kind, string $word): KitchenToolOtherName
    {
        $word = $this->normalize($word);

        if ($word === '') {
            throw new KitchenToolRefused('Enter a name for the tool.');
        }

        if ($existing = $this->resolve($word)) {
            throw new KitchenToolRefused("\"{$word}\" is already known as ".Str::title($existing->name).'.');
        }

        return $kind->otherNames()->create([
            'name' => $word,
            'origin' => KitchenToolKind::ORIGIN_HOUSEHOLD,
        ]);
    }

    /**
     * Remove a household other name. Catalog other names cannot be removed.
     *
     * @throws KitchenToolRefused
     */
    public function removeOtherName(KitchenToolOtherName $otherName): void
    {
        if ($otherName->origin !== KitchenToolKind::ORIGIN_HOUSEHOLD) {
            throw new KitchenToolRefused('"'.Str::title($otherName->name).'" is a built-in name and cannot be removed.');
        }

        $otherName->delete();
    }

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
