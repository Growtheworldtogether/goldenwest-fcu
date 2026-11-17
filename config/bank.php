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
    'wire_fee' => 2_500,                    // $25 per outgoing wire, charged to the sending account
    'wire_cutoff' => '14:00',               // wires reviewed and sent before this time go out the same business day
    'wire_timezone' => 'America/Los_Angeles',
    'overdraft_transfer_fee' => 0,          // charged to the checking account when protection is used

    // Virtual card: default limits a member can lower but not raise.
    'card_daily_limit' => 100_000,    // $1,000 a day
    'card_purchase_limit' => 50_000,  // $500 a purchase

    // Loans
    'loan_grace_days' => 10,          // days after the due date before a late fee
    'loan_late_fee' => 2_500,         // $25
    'loan_delinquent_days' => 30,     // days past due before the loan is marked delinquent
    'loan_first_due_days' => 30,      // first payment is due this many days after disbursement
];
