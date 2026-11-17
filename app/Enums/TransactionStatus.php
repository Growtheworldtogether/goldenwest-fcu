<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reversed = 'reversed';

    /** Statuses where money is reserved but not yet posted. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Held;
    }
}
