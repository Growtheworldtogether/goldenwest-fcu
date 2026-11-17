<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Biller extends Model
{
    protected $guarded = [];

    public function categoryLabel(): string
    {
        return config('spending.biller_categories')[$this->category] ?? ucfirst($this->category);
    }
}
