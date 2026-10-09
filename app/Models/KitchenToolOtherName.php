<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenToolOtherName extends Model
{
    protected $fillable = ['kitchen_tool_kind_id', 'name', 'origin'];

    /**
     * @return BelongsTo<KitchenToolKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(KitchenToolKind::class, 'kitchen_tool_kind_id');
    }
}
