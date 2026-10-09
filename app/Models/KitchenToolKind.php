<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenToolKind extends Model
{
    public const ORIGIN_CATALOG = 'catalog';

    public const ORIGIN_HOUSEHOLD = 'household';

    protected $fillable = ['name', 'origin', 'owned', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owned' => 'boolean',
        ];
    }

    /**
     * @return HasMany<KitchenToolOtherName, $this>
     */
    public function otherNames(): HasMany
    {
        return $this->hasMany(KitchenToolOtherName::class);
    }
}
