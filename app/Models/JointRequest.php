<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JointRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function decisions(): MorphMany
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function lastDecision(): ?Decision
    {
        return $this->decisions()->latest('id')->first();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['invited', 'review', 'consent'], true);
    }
}
