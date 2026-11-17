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

    public function isInternational(): bool
    {
        return $this->kind === 'international';
    }

    public function label(): string
    {
        $where = $this->isInternational() ? ', '.(config('wires.countries')[$this->country] ?? $this->country) : '';

        return $this->name.', '.$this->bank_name.$where.' ending '.$this->last4;
    }
}
