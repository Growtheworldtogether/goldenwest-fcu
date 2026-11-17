<?php

namespace App\Services\Ledger;

use App\Enums\TransactionType;
use App\Support\Money;

/**
 * What a caller asks TransferService to do. A null account on one side means money enters or
 * leaves the credit union (cash, ACH, a loan fund), so the ledger has a single entry for it.
 */
final readonly class TransferRequest
{
    public function __construct(
        public TransactionType $type,
        public Money $amount,
        public string $idempotencyKey,
        public ?int $fromAccountId = null,
        public ?int $toAccountId = null,
        public ?Money $fee = null,
        public ?int $initiatedBy = null,
        public ?int $impersonatorId = null,
        public ?string $memo = null,
        public ?string $memberDescription = null,
        public bool $requireReview = false,
        public ?int $payeeId = null,
        public ?int $categoryId = null,
        public ?string $effectiveDate = null,
    ) {
    }

    public function feeOrZero(): Money
    {
        return $this->fee ?? Money::zero();
    }
}
