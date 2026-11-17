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
