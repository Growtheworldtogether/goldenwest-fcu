<?php

namespace App\Support;

use InvalidArgumentException;

/** Crypto amounts are whole numbers of the smallest unit. This turns them into text and back without floats. */
final class CryptoAmount
{
    public static function parse(string $input, int $decimals): int
    {
        $input = trim(str_replace([',', ' '], '', $input));
        if (! preg_match('/^\d+(\.\d+)?$/', $input)) {
            throw new InvalidArgumentException('Enter the amount as a number, for example 0.25.');
        }
        [$whole, $frac] = array_pad(explode('.', $input), 2, '');
        if (strlen($frac) > $decimals) {
            throw new InvalidArgumentException("This asset allows up to {$decimals} decimal places.");
        }
        if (strlen($whole) > 11) {
            throw new InvalidArgumentException('That amount is too large.');
        }

        return (int) ($whole.str_pad($frac, $decimals, '0'));
    }

    public static function format(int $units, int $decimals): string
    {
        $s = str_pad((string) $units, $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($s, 0, -$decimals) ?: '0';
        $frac = rtrim(substr($s, -$decimals), '0');
        $frac = str_pad($frac, min(2, $decimals), '0');

        return number_format((int) $whole).($frac !== '' ? '.'.$frac : '');
    }
}
