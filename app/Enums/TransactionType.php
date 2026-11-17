<?php

namespace App\Enums;

enum TransactionType: string
{
    case Transfer = 'transfer';               // between a member's own accounts
    case MemberTransfer = 'member_transfer';  // to another member by member number
    case Deposit = 'deposit';                 // cash, check or ACH pull coming in
    case Withdrawal = 'withdrawal';           // cash out
    case AchOut = 'ach_out';
    case WireOut = 'wire_out';
    case CardPurchase = 'card_purchase';
    case BillPayment = 'bill_payment';
    case Adjustment = 'adjustment';           // admin credit or debit
    case Dividend = 'dividend';
    case LoanDisbursement = 'loan_disbursement';
    case OverdraftTransfer = 'overdraft_transfer';
    case Fee = 'fee';
    case Reversal = 'reversal';

    /** Types a member starts, which are subject to per-transaction and daily limits. */
    public function isLimited(): bool
    {
        return in_array($this, [self::Transfer, self::MemberTransfer, self::AchOut, self::WireOut, self::CardPurchase, self::BillPayment], true);
    }

    /** Types that always need both an account to take from and one to pay into. */
    public function isInternal(): bool
    {
        return in_array($this, [self::Transfer, self::MemberTransfer, self::OverdraftTransfer], true);
    }

    /** Types that leave the credit union and always go to staff review. */
    public function alwaysNeedsReview(): bool
    {
        return in_array($this, [self::WireOut], true);
    }
}
