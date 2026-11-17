<?php

namespace App\Support;

/** American Bankers Association routing numbers carry a checksum: 3(d1+d4+d7) + 7(d2+d5+d8) + (d3+d6+d9) divisible by 10. */
final class Routing
{
    public static function valid(string $number): bool
    {
        if (! preg_match('/^\d{9}$/', $number)) {
            return false;
        }
        $d = array_map('intval', str_split($number));
        $sum = 3 * ($d[0] + $d[3] + $d[6]) + 7 * ($d[1] + $d[4] + $d[7]) + ($d[2] + $d[5] + $d[8]);

        return $sum % 10 === 0 && $sum > 0;
    }
}
