<?php

namespace App\Models;

use App\Enums\KitchenToolOrigin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KitchenToolOtherName extends Model
{
    protected $fillable = ['kitchen_tool_kind_id', 'name', 'origin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => KitchenToolOrigin::class,
        ];
    }

    /**
     * The name as shown to the household (the stored name is lowercase).
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => Str::title($this->name));
    }

    /**
     * @return BelongsTo<KitchenToolKind, $this>
     */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(KitchenToolKind::class, 'kitchen_tool_kind_id');
    }
}
