<?php

namespace App\Models;

use Database\Factories\BrainNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A preference note pulled from the second-brain vault repo by brain:sync.
 * Keyed by vault-relative path; content feeds discovery taste profiles.
 */
class BrainNote extends Model
{
    /** @use HasFactory<BrainNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'content',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }
}
