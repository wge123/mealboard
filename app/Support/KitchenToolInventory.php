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

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
