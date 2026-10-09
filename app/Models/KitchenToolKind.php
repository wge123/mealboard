<?php

namespace App\Models;

use App\Enums\KitchenToolOrigin;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KitchenToolKind extends Model
{
    protected $fillable = ['name', 'origin', 'owned', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owned' => 'boolean',
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
     * @return HasMany<KitchenToolOtherName, $this>
     */
    public function otherNames(): HasMany
    {
        return $this->hasMany(KitchenToolOtherName::class);
    }
}
