<?php

// Money rules. All amounts are integer cents. Admin-editable settings arrive with the admin area.
return [
    'region_states' => ['CA', 'NV', 'AZ', 'OR', 'WA'],

    'per_transaction_limit' => 2_500_000,   // $25,000 per member-initiated transaction
    'default_daily_limit' => 1_000_000,     // $10,000 per account per day when the account has none
    'review_threshold' => 500_000,          // $5,000 and over waits for staff review
    'dual_approval_threshold' => 2_500_000, // $25,000 and over needs a second admin
    'check_deposit_limit' => 1_000_000,     // $10,000 per mobile check deposit
    'ach_pull_limit' => 1_000_000,          // $10,000 per transfer from another bank
    'cash_review_threshold' => 1_000_000,   // cash at the counter of $10,000 or more waits for a second person
    'payee_cooling_hours' => 24,            // a new external account cannot be used until this many hours pass
    'overdraft_transfer_fee' => 0,          // charged to the checking account when protection is used
];
