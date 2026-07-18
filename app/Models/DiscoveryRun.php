<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscoveryRun extends Model
{
    /** @use HasFactory<\Database\Factories\DiscoveryRunFactory> */
    use HasFactory;

    protected $fillable = [
        'ran_at',
        'driver',
        'candidates_found',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'candidates_found' => 'integer',
        ];
    }
}
