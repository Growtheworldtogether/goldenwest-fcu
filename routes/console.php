<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resolved support tickets close by themselves after a few days.
Artisan::command('tickets:autoclose', function () {
    $n = app(\App\Services\TicketService::class)->autoClose();
    $this->info("Closed {$n} resolved ".\Illuminate\Support\Str::plural('ticket', $n).'.');
})->purpose('Close resolved tickets after the set number of days')->dailyAt('03:15');

// Loans: autopay what is due, add late fees after the grace period, flag delinquent loans.
Artisan::command('loans:process', function () {
    $r = app(\App\Services\Loans\LoanService::class)->processDaily();
    $this->info("Autopaid {$r['paid']}, autopay failed {$r['failed']}, late fees {$r['fees']}.");
})->purpose('Run daily loan autopay, late fees and delinquency')->dailyAt('04:00');

// Member extras: scheduled bill payments, automatic goal saving and the monthly spending summary.
Artisan::command('bills:process', function () {
    [$sent, $delivered] = app(\App\Services\BillPayService::class)->processDaily();
    $this->info("Sent {$sent} bill payments, {$delivered} delivered.");
})->purpose('Send due bill payments and mark delivered ones')->dailyAt('05:00');

Artisan::command('goals:run', function () {
    $n = app(\App\Services\GoalService::class)->runAuto();
    $this->info("Made {$n} automatic goal contributions.");
})->purpose('Make due automatic savings contributions')->dailyAt('05:30');

Artisan::command('insights:summarize {month?}', function (?string $month = null) {
    $m = $month ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01') : today()->subMonth();
    $n = app(\App\Services\SpendingService::class)->summarize($m->startOfMonth());
    $this->info("Wrote {$n} summary rows for ".$m->format('Y-m').'.');
})->purpose('Store last month\'s spending totals')->monthlyOn(1, '06:00');
