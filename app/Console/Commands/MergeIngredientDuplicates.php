<?php

namespace App\Console\Commands;

use App\Actions\Recipes\SyncRecipeIngredients;
use App\Models\Ingredient;
use App\Models\WalmartMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ingredients imported before names were cleaned still hold whole recipe
 * lines ("200g tomato", "pinch of salt"). Clean every name the way import
 * now does and fold each group that cleans to one name into one row: recipe
 * links move over (their amount filled from the old name when the link had
 * none), the pantry-staple flag survives if any row had it, a Walmart match
 * is kept when the survivor has none, and the other rows are deleted.
 */
class MergeIngredientDuplicates extends Command
{
    protected $signature = 'ingredients:merge-duplicates {--dry-run : Show what would merge without writing}';

    protected $description = 'Clean ingredient names and merge the rows that clean to the same name';

    public function handle(SyncRecipeIngredients $sync): int
    {
        $groups = Ingredient::query()->orderBy('id')->get()
            ->groupBy(fn (Ingredient $ingredient) => $sync->clean($ingredient->name)['name'])
            ->filter(fn (Collection $group, string $clean) => $group->count() > 1 || $group->first()->name !== $clean);

        if ($groups->isEmpty()) {
            $this->info('Every ingredient name is already clean.');

            return self::SUCCESS;
        }

        foreach ($groups as $clean => $group) {
            $survivor = $group->firstWhere('name', $clean) ?? $group->first();
            $others = $group->reject(fn (Ingredient $ingredient) => $ingredient->is($survivor))->values();

            $this->line(sprintf(
                '"%s" <- %s',
                $clean,
                $group->map(fn (Ingredient $ingredient) => '"'.$ingredient->name.'"')->implode(', '),
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($sync, $survivor, $others, $clean) {
                foreach ($others as $other) {
                    $this->moveRecipeLinks($sync, $other, $survivor);

                    if ($other->is_pantry_staple) {
                        $survivor->is_pantry_staple = true;
                    }

                    if ($survivor->walmartMatch()->doesntExist()) {
                        WalmartMatch::query()->where('ingredient_id', $other->id)->update(['ingredient_id' => $survivor->id]);
                    }

                    $other->delete();
                }

                if ($survivor->name !== $clean) {
                    $this->fillLinkAmounts($sync, $survivor);
                    $survivor->name = $clean;
                }

                $survivor->save();
            });
        }

        $this->info($this->option('dry-run')
            ? "{$groups->count()} name(s) would change; run without --dry-run to apply."
            : "{$groups->count()} name(s) cleaned.");

        return self::SUCCESS;
    }

    /**
     * Re-point the duplicate's recipe links at the survivor, with the amount
     * its old name carried. A recipe already linked to the survivor keeps
     * that link and the duplicate's is dropped.
     */
    private function moveRecipeLinks(SyncRecipeIngredients $sync, Ingredient $from, Ingredient $to): void
    {
        $this->fillLinkAmounts($sync, $from);

        foreach (DB::table('ingredient_recipe')->where('ingredient_id', $from->id)->get() as $link) {
            $taken = DB::table('ingredient_recipe')
                ->where('ingredient_id', $to->id)
                ->where('recipe_id', $link->recipe_id)
                ->exists();

            $query = DB::table('ingredient_recipe')->where('id', $link->id);

            $taken ? $query->delete() : $query->update(['ingredient_id' => $to->id]);
        }
    }

    /**
     * A link whose amount is empty gets the amount the ingredient's uncleaned
     * name carried ("200g tomato" -> 200 g; "pinch of salt" -> note "pinch of").
     */
    private function fillLinkAmounts(SyncRecipeIngredients $sync, Ingredient $ingredient): void
    {
        $parsed = $sync->clean($ingredient->name);

        foreach (DB::table('ingredient_recipe')->where('ingredient_id', $ingredient->id)->get() as $link) {
            DB::table('ingredient_recipe')->where('id', $link->id)->update([
                'qty' => $link->qty ?? $parsed['qty'],
                'unit' => $link->unit ?? $parsed['unit'],
                'note' => implode(', ', array_filter([$link->note, $parsed['note']])) ?: null,
            ]);
        }
    }
}
