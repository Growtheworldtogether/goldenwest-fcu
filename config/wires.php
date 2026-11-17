<?php

/** Where international wires can go. A country not listed here is not offered. */
return [
    'countries' => [
        'AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BR' => 'Brazil', 'CA' => 'Canada',
        'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'DO' => 'Dominican Republic',
        'EG' => 'Egypt', 'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala', 'HK' => 'Hong Kong',
        'IN' => 'India', 'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'KE' => 'Kenya',
        'MY' => 'Malaysia', 'MX' => 'Mexico', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'NO' => 'Norway', 'PK' => 'Pakistan',
        'PA' => 'Panama', 'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'SA' => 'Saudi Arabia', 'SG' => 'Singapore',
        'ZA' => 'South Africa', 'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'TH' => 'Thailand',
        'TT' => 'Trinidad and Tobago', 'TR' => 'Turkey', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'VN' => 'Vietnam',
    ],

    // Countries whose banks use IBANs. The recipient's account must then be a valid IBAN from that country.
    'iban' => ['AT', 'BE', 'BR', 'CH', 'CR', 'CZ', 'DE', 'DK', 'DO', 'ES', 'FI', 'FR', 'GB', 'GR', 'IE', 'IL', 'IT', 'NL', 'NO', 'PK', 'PL', 'PT', 'SA', 'SE', 'TR', 'AE'],

    // Never offered, even if someone types the code: sanctioned or embargoed destinations.
    'blocked' => ['CU', 'IR', 'KP', 'SY', 'RU', 'BY'],
];
