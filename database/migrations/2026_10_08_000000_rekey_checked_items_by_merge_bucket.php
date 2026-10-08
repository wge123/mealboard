<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Checked shopping list lines used to be keyed `name|display unit`; they are
 * now keyed `name|merge bucket`, so a check survives a recipe edit that moves
 * the amount across a display threshold. Rewrite the stored keys so the week
 * being shopped keeps its checks.
 */
return new class extends Migration
{
    /** Display unit => merge bucket, as the Shopping list module had them on this date. */
    private const array BUCKETS = [
        'tsp' => 'spoon', 'tbsp' => 'spoon', 'cup' => 'spoon',
        'g' => 'mass', 'kg' => 'mass',
        'ml' => 'volume', 'l' => 'volume',
    ];

    public function up(): void
    {
        DB::table('meal_plans')->whereNotNull('checked_items')->orderBy('id')->each(function (object $plan) {
            $keys = json_decode($plan->checked_items, true, flags: JSON_THROW_ON_ERROR);

            $rekeyed = array_values(array_unique(array_map($this->rekey(...), $keys)));

            DB::table('meal_plans')->where('id', $plan->id)->update(['checked_items' => json_encode($rekeyed)]);
        });
    }

    /**
     * Not reversible: a bucket does not say which display unit the line had.
     */
    public function down(): void
    {
        throw new LogicException('Rekeying checked items by merge bucket is not reversible: a bucket does not say which display unit the line had.');
    }

    private function rekey(string $key): string
    {
        $separator = strrpos($key, '|');

        // Every key the page ever wrote is `name|unit`; one without the
        // separator is corrupt, and passing it through would hide that.
        if ($separator === false) {
            throw new UnexpectedValueException("Checked key \"{$key}\" has no \"|\" separator; fix the stored checked_items before migrating.");
        }

        $unit = substr($key, $separator + 1);

        return substr($key, 0, $separator + 1).(self::BUCKETS[$unit] ?? $unit);
    }
};
