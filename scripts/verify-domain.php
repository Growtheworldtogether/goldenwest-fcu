<?php

declare(strict_types=1);

/**
 * Framework-free checks for the pure domain code (money, loan schedule, card numbers).
 * Run with: php scripts/verify-domain.php
 * These become Pest tests once Laravel is installed (see docs/SPEC.md, milestone M1).
 */

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Services\Loans\AmortizationSchedule;
use App\Support\CardNumber;
use App\Support\Money;

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok   {$name}\n";
    } else {
        $failed++;
        echo "  FAIL {$name}\n";
    }
}

function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable) {
        return true;
    }

    return false;
}

echo "Money\n";
check('parse dollars with symbols', Money::parse('$1,234.50')->cents === 123450);
check('parse one decimal digit', Money::parse('7.5')->cents === 750);
check('parse negative', Money::parse('-0.07')->cents === -7);
check('parse rejects junk', throws(fn () => Money::parse('12.345')));
check('parse rejects letters', throws(fn () => Money::parse('abc')));
check('add and subtract', Money::cents(1000)->add(Money::cents(250))->subtract(Money::cents(50))->cents === 1200);
check('format positive', Money::cents(123456789)->format() === '$1,234,567.89');
check('format small', Money::cents(5)->format() === '$0.05');
check('format negative', Money::cents(-150)->format() === '-$1.50');
check('compare', Money::cents(5)->lessThan(Money::cents(6)) && Money::cents(6)->greaterThan(Money::cents(5)));
$parts = Money::cents(100)->allocate([1, 1, 1]);
check('allocate keeps the total', array_sum(array_map(fn (Money $m) => $m->cents, $parts)) === 100);
check('allocate gives remainder to first', $parts[0]->cents === 34 && $parts[1]->cents === 33 && $parts[2]->cents === 33);

echo "Loan schedule\n";
$start = new DateTimeImmutable('2026-12-01');

$a = AmortizationSchedule::build(Money::parse('10000'), 600, 12, $start); // 6% for 12 months
check('10,000 at 6% for 12 months pays 860.66', $a[0]['payment']->cents === 86066);
check('ends at zero balance', end($a)['balance_after']->cents === 0);
check('principal parts add up', array_sum(array_map(fn ($r) => $r['principal']->cents, $a)) === 1000000);
check('has 12 rows', count($a) === 12);
check('first interest is 50.00', $a[0]['interest']->cents === 5000);

$b = AmortizationSchedule::build(Money::parse('20000'), 500, 60, $start); // 5% for 60 months
check('20,000 at 5% for 60 months pays 377.42', $b[0]['payment']->cents === 37742);
check('60-month schedule ends at zero', end($b)['balance_after']->cents === 0);
check('balance never goes negative', count(array_filter($b, fn ($r) => $r['balance_after']->cents < 0)) === 0);
check('last payment close to level payment', abs(end($b)['payment']->cents - 37742) < 100);

$c = AmortizationSchedule::build(Money::parse('1000'), 0, 3, $start); // 0% loan
check('0% loan splits evenly with remainder last', $c[0]['payment']->cents === 33334 && $c[2]['payment']->cents === 33332);
check('0% loan has no interest', array_sum(array_map(fn ($r) => $r['interest']->cents, $c)) === 0);

check('month-end clamping Jan 31 to Feb 28', AmortizationSchedule::addMonthsClamped(new DateTimeImmutable('2026-01-31'), 1)->format('Y-m-d') === '2026-02-28');
check('leap year Feb 29', AmortizationSchedule::addMonthsClamped(new DateTimeImmutable('2028-01-31'), 1)->format('Y-m-d') === '2028-02-29');
check('day returns to 31 in March', AmortizationSchedule::addMonthsClamped(new DateTimeImmutable('2026-01-31'), 2)->format('Y-m-d') === '2026-03-31');
check('rejects zero principal', throws(fn () => AmortizationSchedule::build(Money::zero(), 500, 12, $start)));
check('rejects zero term', throws(fn () => AmortizationSchedule::build(Money::cents(100), 500, 0, $start)));

echo "Card numbers\n";
check('known good Luhn number', CardNumber::isValid('4111111111111111'));
check('known bad Luhn number', !CardNumber::isValid('4111111111111112'));
$card = CardNumber::fromSerial(1);
check('generated number has 16 digits', strlen($card) === 16 && ctype_digit($card));
check('generated number passes Luhn', CardNumber::isValid($card));
check('generated number uses the fake prefix', str_starts_with($card, CardNumber::TEST_PREFIX));
check('same serial gives the same number', CardNumber::fromSerial(42) === CardNumber::fromSerial(42));
check('different serials differ', CardNumber::fromSerial(42) !== CardNumber::fromSerial(43));
check('mask keeps last four', CardNumber::mask($card) === str_repeat('*', 12) . substr($card, -4));
check('every one of 500 serials passes Luhn', (function () {
    for ($i = 0; $i < 500; $i++) {
        if (!CardNumber::isValid(CardNumber::fromSerial($i))) {
            return false;
        }
    }

    return true;
})());

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
