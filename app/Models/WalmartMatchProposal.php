<?php

namespace App\Models;

use Database\Factories\WalmartMatchProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An unreviewed product guess for an ingredient. It never reaches the cart
 * link: only ReviewProductMatch turns it into a WalmartMatch.
 */
class WalmartMatchProposal extends Model
{
    /** @use HasFactory<WalmartMatchProposalFactory> */
    use HasFactory;

    public const CONFIDENCES = ['high', 'medium', 'low'];

    protected $fillable = [
        'ingredient_id',
        'product_url',
        'product_name',
        'confidence',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
