<?php

namespace App\Actions\Planning;

use App\Models\PlannedMeal;
use Illuminate\Support\Collection;

/**
 * What one auto-fill did: the meals it created, and the reasons a slot it was
 * asked to fill stayed empty (expected outcomes, not errors).
 */
final readonly class AutoFillResult
{
    /**
     * @param  Collection<int, PlannedMeal>  $meals
     * @param  list<string>  $unfilled  one plain sentence per slot left empty, for the week notice
     */
    public function __construct(public Collection $meals, public array $unfilled = []) {}
}
