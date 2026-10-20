<?php

namespace App\Models;

use Database\Factories\RecipeSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeSite extends Model
{
    /** @use HasFactory<RecipeSiteFactory> */
    use HasFactory;

    protected $table = 'recipe_sites';

    protected $fillable = [
        'feed_url',
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
