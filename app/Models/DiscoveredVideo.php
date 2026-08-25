<?php

namespace App\Models;

use App\Enums\VideoClassification;
use Database\Factories\DiscoveredVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredVideo extends Model
{
    /** @use HasFactory<DiscoveredVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'video_id',
        'channel_id',
        'recipe_request_id',
        'title',
        'description',
        'published_at',
        'classification',
        'score',
        'processed_at',
        'error',
    ];

    /**
     * Set when the row came from a household request's keyword search rather
     * than from a subscribed channel's feed.
     *
     * @return BelongsTo<RecipeRequest, $this>
     */
    public function recipeRequest(): BelongsTo
    {
        return $this->belongsTo(RecipeRequest::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'classification' => VideoClassification::class,
            'score' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
