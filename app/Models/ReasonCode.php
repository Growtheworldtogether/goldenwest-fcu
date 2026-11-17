<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReasonCode extends Model
{
    protected $fillable = ['code', 'label', 'applies_to'];
}
