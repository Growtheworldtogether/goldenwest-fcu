<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;

/** Generates unique, realistic account and member numbers. */
final class AccountNumber
{
    /** An 11-digit account number, shown masked as ••••1234 everywhere it appears. */
    public static function account(): string
    {
        do {
            $number = (string) random_int(1, 9).str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        } while (Account::where('account_number', $number)->exists());

        return $number;
    }

    /** A 7-digit member number. */
    public static function member(): string
    {
        do {
            $number = (string) random_int(1_000_000, 9_999_999);
        } while (User::where('member_number', $number)->exists());

        return $number;
    }
}
