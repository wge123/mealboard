<?php

namespace App\Models;

use App\Enums\VideoClassification;
use Database\Factories\DiscoveredVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscoveredVideo extends Model
{
    /** @use HasFactory<DiscoveredVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'video_id',
        'channel_id',
        'title',
        'description',
        'published_at',
        'classification',
        'score',
        'processed_at',
        'error',
    ];

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
