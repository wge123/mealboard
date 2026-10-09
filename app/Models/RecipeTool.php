<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeTool extends Model
{
    protected $fillable = ['position', 'count'];

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return HasMany<RecipeToolAlternative, $this> */
    public function alternatives(): HasMany
    {
        return $this->hasMany(RecipeToolAlternative::class)->orderBy('position');
    }
}
