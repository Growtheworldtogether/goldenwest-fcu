<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $guarded = [];
    protected $casts = ['published' => 'array', 'draft' => 'array', 'published_at' => 'datetime', 'draft_at' => 'datetime'];
}
