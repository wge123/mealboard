<?php

namespace App\Models;

use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'source_url',
        'source',
        'recipe_request_id',
        'status',
        'meal_type',
        'prep_minutes',
        'cook_minutes',
        'servings',
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

    /** @return BelongsTo<RecipeRequest, $this> */
    public function recipeRequest(): BelongsTo
    {
        return $this->belongsTo(RecipeRequest::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['qty', 'unit', 'note'])
            ->withTimestamps();
    }

    /** @return HasMany<RecipeTool, $this> */
    public function recipeTools(): HasMany
    {
        return $this->hasMany(RecipeTool::class)->orderBy('position');
    }

    /** @return HasMany<CookingStep, $this> */
    public function cookingSteps(): HasMany
    {
        return $this->hasMany(CookingStep::class)->orderBy('position');
    }

    /**
     * Whether the recipe carries the recipe shape: tools, ingredients and cooking steps.
     */
    public function hasShape(): bool
    {
        return $this->recipeTools()->exists()
            && $this->cookingSteps()->exists()
            && $this->ingredients()->exists();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The YouTube video id when source_url is a YouTube URL, else null. The
     * thumbnail is derivable by convention (see YouTubeDriver):
     * https://i.ytimg.com/vi/{video_id}/hqdefault.jpg
     */
    public function youtubeVideoId(): ?string
    {
        if ($this->source_url === null) {
            return null;
        }

        $matched = preg_match(
            '~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~',
            $this->source_url,
            $m,
        );

        return $matched === 1 ? $m[1] : null;
    }
}
