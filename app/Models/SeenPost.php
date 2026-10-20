<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A recipe-site post address the daily discovery has fetched, whatever came of
 * it, so the page is never fetched twice.
 */
class SeenPost extends Model
{
    protected $fillable = ['url'];
}
