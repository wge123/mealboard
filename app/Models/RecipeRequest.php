<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\RecipeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One household ask for a specific dish ("hibachi for 4 on a flat-top
 * griddle"). Unlike scheduled discovery, a request is exempt from the
 * weeknight caps (30 minutes total, 10 ingredients): the household asked for
 * this dish by name, so the dish decides its own shape.
 */
class RecipeRequest extends Model
{
    /** @use HasFactory<RecipeRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'query',
        'status',
        'candidates_found',
        'error',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'candidates_found' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Recipe, $this> */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /** @return HasMany<DiscoveredVideo, $this> */
    public function videos(): HasMany
    {
        return $this->hasMany(DiscoveredVideo::class);
    }
}
