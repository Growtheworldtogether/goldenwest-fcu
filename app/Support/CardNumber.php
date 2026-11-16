<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Fake virtual-card numbers. They carry a valid Luhn check digit but start with a
 * prefix that no real card network issues, so they can never collide with a real card.
 */
final class CardNumber
{
    public const TEST_PREFIX = '999999';

    /** Build a 16-digit number from a serial. Same serial always gives the same number. */
    public static function fromSerial(int $serial, string $prefix = self::TEST_PREFIX): string
    {
        if ($serial < 0 || strlen($prefix) > 9 || !ctype_digit($prefix)) {
            throw new InvalidArgumentException('Invalid card serial or prefix.');
        }

        $body = $prefix . str_pad((string) $serial, 15 - strlen($prefix), '0', STR_PAD_LEFT);
        if (strlen($body) !== 15) {
            throw new InvalidArgumentException('Serial too large for this prefix.');
        }

        return $body . self::checkDigit($body);
    }

    public static function isValid(string $number): bool
    {
        if (!ctype_digit($number) || strlen($number) < 12) {
            return false;
        }

        return self::checkDigit(substr($number, 0, -1)) === (int) substr($number, -1);
    }

    /** Luhn check digit for a number without its last digit. */
    public static function checkDigit(string $body): int
    {
        $sum = 0;
        $double = true;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $d = (int) $body[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /** Mask all but the last four digits. */
    public static function mask(string $number): string
    {
        return str_repeat('*', max(0, strlen($number) - 4)) . substr($number, -4);
    }
}
