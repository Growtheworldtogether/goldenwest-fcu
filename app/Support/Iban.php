<?php

namespace App\Support;

/** Checks the shape and check digits of an IBAN. It cannot tell whether the account exists. */
final class Iban
{
    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    public static function valid(string $iban, ?string $country = null): bool
    {
        $iban = self::normalize($iban);
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) || ($country && substr($iban, 0, 2) !== strtoupper($country))) {
            return false;
        }
        $moved = substr($iban, 4).substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($moved) as $ch) {
            $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rem = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rem = (int) ($rem.$chunk) % 97;
        }

        return $rem === 1;
    }
}
