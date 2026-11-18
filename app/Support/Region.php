<?php

namespace App\Support;

use App\Models\Setting;

/** The states where membership is open. A Super Admin can change the list in Settings. */
class Region
{
    public const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
        'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
        'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
        'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];

    /** Two-letter codes of the eligible states, in A to Z order. */
    public static function codes(): array
    {
        $saved = Setting::find('region_states')?->value;
        $list = $saved !== null ? (json_decode($saved, true) ?: []) : config('bank.region_states');
        $list = array_values(array_intersect(array_keys(self::STATES), $list));

        return $list ?: config('bank.region_states');
    }

    public static function save(array $codes): void
    {
        $codes = array_values(array_intersect(array_keys(self::STATES), $codes));
        Setting::set('region_states', json_encode($codes));
    }

    /** "California, Nevada and Washington" (or "... or Washington"). */
    public static function sentence(string $joiner = 'and'): string
    {
        $names = array_map(fn ($c) => self::STATES[$c], self::codes());
        $last = array_pop($names);

        return $names ? implode(', ', $names).' '.$joiner.' '.$last : $last;
    }
}
