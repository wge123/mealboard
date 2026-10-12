<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Updates a set of rows by id only when every id still names a row. The rows
 * are locked while they are counted, so none can vanish between the check
 * and the write.
 */
class AllOrNothingUpdate
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $values
     * @return bool false when an id names no row; nothing is written then
     */
    public static function apply(Builder $query, array $ids, array $values): bool
    {
        $ids = array_values(array_unique($ids));

        return DB::transaction(function () use ($query, $ids, $values) {
            // Counted rather than read from update(): MySQL reports changed rows, not matched ones.
            if ((clone $query)->whereKey($ids)->lockForUpdate()->count() !== count($ids)) {
                return false;
            }

            (clone $query)->whereKey($ids)->update($values);

            return true;
        });
    }
}
