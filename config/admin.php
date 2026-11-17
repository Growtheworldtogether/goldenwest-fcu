<?php

// Staff titles and what each may do. Super Admin can adjust these presets later; the config is the
// starting point that `php artisan admin:sync` and the seeder write to the permission tables.
return [
    'permissions' => [
        'members.lookup' => 'Look up members',
        'deposits.post' => 'Post cash and check deposits',
        'transactions.approve.small' => 'Approve, reject or hold small transactions',
        'transactions.approve' => 'Approve, reject or hold any transaction',
        'accounts.lock' => 'Lock and unlock accounts',
        'balances.adjust.limited' => 'Adjust balances up to a limit',
        'balances.adjust' => 'Adjust balances',
        'kyc.review' => 'Review KYC',
        'audit.view' => 'View audit log',
        'credentials.reset' => 'Reset credentials',
        'tickets.answer' => 'Answer inquiries and tickets',
        'approvals.manage' => 'Approve cards, loans and crypto sends',
        'features.toggle' => 'Toggle per-user features',
        'impersonate' => 'Log in as member',
        'site.edit' => 'Edit branding and public site content',
        'admin.manage' => 'Manage admins, settings and announcements',
    ],

    'titles' => [
        'Teller' => ['members.lookup', 'deposits.post', 'transactions.approve.small'],
        'Manager' => ['members.lookup', 'deposits.post', 'transactions.approve', 'accounts.lock', 'balances.adjust.limited', 'audit.view', 'tickets.answer', 'approvals.manage', 'features.toggle', 'impersonate'],
        'Compliance' => ['members.lookup', 'transactions.approve', 'accounts.lock', 'kyc.review', 'audit.view', 'tickets.answer', 'approvals.manage', 'impersonate'],
        'Support Agent' => ['members.lookup', 'credentials.reset', 'tickets.answer', 'impersonate'],
        'Super Admin' => ['*'],
    ],

    'teller_approval_limit' => 100_000,      // $1,000
    'manager_adjustment_limit' => 500_000,   // $5,000
    'idle_timeout_minutes' => 10,
    'impersonation_minutes' => 30,
    'impersonation_allow_money_actions' => true,
];
