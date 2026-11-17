<?php

/** Simulated crypto. Nothing real is held, traded or sent. Address rules only catch obvious typos. */
return [
    'networks' => [
        'Bitcoin' => ['pattern' => '/^(bc1[a-z0-9]{25,60}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', 'example' => 'bc1q... or 1... or 3...', 'memo' => false],
        'Ethereum' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'example' => '0x followed by 40 letters and numbers', 'memo' => false],
        'Tron' => ['pattern' => '/^T[a-zA-Z0-9]{33}$/', 'example' => 'T followed by 33 letters and numbers', 'memo' => false],
        'Litecoin' => ['pattern' => '/^(ltc1[a-z0-9]{25,60}|[LM3][a-km-zA-HJ-NP-Z1-9]{26,33})$/', 'example' => 'ltc1... or L... or M...', 'memo' => false],
        'Solana' => ['pattern' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', 'example' => '32 to 44 letters and numbers', 'memo' => true],
    ],
    'max_pending_sends' => 5,
    'max_pending_deposits' => 5,
];
