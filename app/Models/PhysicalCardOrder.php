<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalCardOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decisions()
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function addressLine(): string
    {
        return trim($this->address_line1.' '.$this->address_line2).', '.$this->city.', '.$this->state.' '.$this->postal_code;
    }
}
