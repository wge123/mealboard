<?php

namespace App\Models;

use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'source_url',
        'source',
        'status',
        'meal_type',
        'prep_minutes',
        'cook_minutes',
        'servings',
        'instructions',
        'cuisine',
        'tags',
        'image_url',
        'discovered_at',
        'approved_at',
        'approved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RecipeSource::class,
            'status' => RecipeStatus::class,
            'meal_type' => MealType::class,
            'tags' => 'array',
            'discovered_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['qty', 'unit', 'note'])
            ->withTimestamps();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
