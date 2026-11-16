<?php

use App\Support\Money;
use App\Support\CardNumber;
use App\Services\Loans\AmortizationSchedule;

it('parses and formats without floats', function () {
    expect(Money::parse('1234.5')->cents)->toBe(123450)
        ->and(Money::parse('-1.50')->format())->toBe('-$1.50')
        ->and(Money::parse('1234.56')->format())->toBe('$1,234.56');
});

it('allocates remainder cents to the first parts', function () {
    $parts = array_map(fn ($m) => $m->cents, Money::parse('1.00')->allocate([1, 1, 1]));
    expect($parts)->toBe([34, 33, 33]);
});

it('builds a loan schedule that amortizes to zero', function () {
    $rows = AmortizationSchedule::build(Money::parse('10000'), 600, 12, new DateTimeImmutable('2026-12-31'));
    expect($rows[0]['payment']->cents)->toBe(86066)
        ->and(end($rows)['balance_after']->cents)->toBe(0);
});

it('generates Luhn-valid test card numbers', function () {
    foreach (range(1, 50) as $i) {
        expect(CardNumber::isValid(CardNumber::fromSerial($i)))->toBeTrue();
    }
});
