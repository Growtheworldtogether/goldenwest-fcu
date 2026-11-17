<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandSetting extends Model
{
    protected $guarded = [];
    protected $casts = ['social_links' => 'array'];
}
