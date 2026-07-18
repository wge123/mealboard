<?php

namespace App\Models;

use Database\Factories\YouTubeChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YouTubeChannel extends Model
{
    /** @use HasFactory<YouTubeChannelFactory> */
    use HasFactory;

    protected $table = 'youtube_channels';

    protected $fillable = [
        'channel_id',
        'name',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
