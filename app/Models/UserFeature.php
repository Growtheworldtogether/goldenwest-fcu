<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFeature extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled_at' => 'datetime', 'disabled_at' => 'datetime'];
    }
}
