<?php

namespace App\Models;

use App\Enums\IngredientCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    /** @use HasFactory<\Database\Factories\IngredientFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'is_pantry_staple',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => IngredientCategory::class,
            'is_pantry_staple' => 'boolean',
        ];
    }

    /**
     * Ingredient names are stored lowercased so the unique index deduplicates
     * "Salt" and "salt".
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)
            ->withPivot(['qty', 'unit', 'note'])
            ->withTimestamps();
    }
}
