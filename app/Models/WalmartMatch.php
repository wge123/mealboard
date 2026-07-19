<?php

namespace App\Models;

use Database\Factories\WalmartMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalmartMatch extends Model
{
    /** @use HasFactory<WalmartMatchFactory> */
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'product_url',
        'product_name',
        'last_confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_confirmed_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
