<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeToolAlternative extends Model
{
    protected $fillable = ['position', 'word', 'kitchen_tool_kind_id'];

    /** @return BelongsTo<RecipeTool, $this> */
    public function recipeTool(): BelongsTo
    {
        return $this->belongsTo(RecipeTool::class);
    }

    /** @return BelongsTo<KitchenToolKind, $this> */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(KitchenToolKind::class, 'kitchen_tool_kind_id');
    }
}
