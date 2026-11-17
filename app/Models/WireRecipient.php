<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WireRecipient extends Model
{
    protected $guarded = [];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted'];
    }

    public function label(): string
    {
        return $this->name.', '.$this->bank_name.' ending '.$this->last4;
    }
}
