<?php

namespace App\Services\Ledger;

use RuntimeException;

/** A money request was refused. `reason` is a stable machine code, the message is safe to show a member. */
class TransferException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason, string $message): self
    {
        return new self($reason, $message);
    }
}
