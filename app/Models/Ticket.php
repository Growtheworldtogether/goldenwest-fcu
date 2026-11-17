<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime', 'closed_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statusLabel(): string
    {
        return config('support.statuses')[$this->status] ?? $this->status;
    }

    public function categoryLabel(): string
    {
        return config('support.categories')[$this->category] ?? ucfirst($this->category);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** What a member may read: internal notes never leave the staff side. */
    public function memberMessages(): HasMany
    {
        return $this->messages()->where('is_internal_note', false);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed'], true);
    }
}
