<?php

namespace App\Support;

use App\Enums\KitchenToolOrigin;
use App\Exceptions\KitchenToolRefused;
use App\Models\KitchenToolKind;
use App\Models\KitchenToolOtherName;
use App\Models\RecipeToolAlternative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The household's kitchen tool inventory: one home for every question about
 * kitchen tool kinds and the writes that change them. Words are matched
 * trimmed and lowercased. Writes take the kind or other name as a model;
 * callers turn a word into a kind with resolve first.
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
     * Whether the household owns this kind.
     */
    public function owns(KitchenToolKind $kind): bool
    {
        return KitchenToolKind::query()
            ->whereKey($kind->getKey())
            ->where('owned', true)
            ->exists();
    }

    /**
     * Mark a kind owned or not owned.
     */
    public function setOwned(KitchenToolKind $kind, bool $owned): void
    {
        $kind->update(['owned' => $owned]);
    }

    /**
     * Set or clear a kind's note. A note is for people only and never takes
     * part in matching; an empty or blank note is stored as no note.
     */
    public function setNote(KitchenToolKind $kind, ?string $note): void
    {
        $note = $note === null ? '' : trim($note);

        $kind->update(['note' => $note === '' ? null : $note]);
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
        $kind = KitchenToolKind::create([
            'name' => $this->newWord($name),
            'origin' => KitchenToolOrigin::Household,
            'owned' => true,
        ]);

        $this->reResolveAlternatives();

        return $kind;
    }

    /**
     * Delete a household kind with its other names. Catalog kinds can only be
     * marked not owned, and a kind a recipe's tool alternative points at
     * cannot be deleted.
     *
     * @throws KitchenToolRefused
     */
    public function deleteKind(KitchenToolKind $kind): void
    {
        if ($kind->origin !== KitchenToolOrigin::Household) {
            throw new KitchenToolRefused("{$kind->display_name} is a catalog kitchen tool and cannot be deleted. Mark it not owned instead.");
        }

        if (RecipeToolAlternative::query()->where('kitchen_tool_kind_id', $kind->id)->exists()) {
            throw new KitchenToolRefused("{$kind->display_name} is used by a recipe's kitchen tools, so it cannot be deleted.");
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
        $otherName = $kind->otherNames()->create([
            'name' => $this->newWord($word),
            'origin' => KitchenToolOrigin::Household,
        ]);

        $this->reResolveAlternatives();

        return $otherName;
    }

    /**
     * Remove a household other name. Catalog other names cannot be removed.
     *
     * @throws KitchenToolRefused
     */
    public function removeOtherName(KitchenToolOtherName $otherName): void
    {
        if ($otherName->origin !== KitchenToolOrigin::Household) {
            throw new KitchenToolRefused("\"{$otherName->display_name}\" is a catalog other name and cannot be removed.");
        }

        $otherName->delete();
    }

    /**
     * The normalised word for a new kind or other name, refusing a blank one
     * and one that already resolves to a kind.
     *
     * @throws KitchenToolRefused
     */
    private function newWord(string $word): string
    {
        $word = $this->normalize($word);

        if ($word === '') {
            throw new KitchenToolRefused('Enter a name for the kitchen tool.');
        }

        if ($existing = $this->resolve($word)) {
            throw new KitchenToolRefused("\"{$word}\" is already known as {$existing->display_name}.");
        }

        return $word;
    }

    /**
     * Give a kind to every recipe tool alternative that has none yet and
     * whose word now resolves, on every recipe, saved ones included. The
     * word-to-kind map is read once (kind names win over other names, as in
     * resolve), then each kind's alternatives are updated in one query.
     */
    private function reResolveAlternatives(): void
    {
        $unresolved = RecipeToolAlternative::query()
            ->whereNull('kitchen_tool_kind_id')
            ->get(['id', 'word']);

        if ($unresolved->isEmpty()) {
            return;
        }

        // The array + keeps the left side's keys, so a kind name wins over an other name.
        $kindIdByWord = KitchenToolKind::query()->pluck('id', 'name')->all()
            + KitchenToolOtherName::query()->pluck('kitchen_tool_kind_id', 'name')->all();

        $unresolved
            ->groupBy(fn (RecipeToolAlternative $alternative) => $kindIdByWord[$this->normalize($alternative->word)] ?? null)
            ->each(function (Collection $alternatives, $kindId) {
                if ($kindId !== '') {
                    RecipeToolAlternative::query()
                        ->whereKey($alternatives->modelKeys())
                        ->update(['kitchen_tool_kind_id' => $kindId]);
                }
            });
    }

    private function normalize(string $word): string
    {
        return mb_strtolower(trim($word));
    }
}
