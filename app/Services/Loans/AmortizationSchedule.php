<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Support\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Builds a fixed-payment loan schedule in integer cents.
 *
 * The level payment is computed once with the standard formula and rounded to the cent.
 * Every row's interest is computed with integer math (half up), and the last payment
 * absorbs any rounding so the balance ends at exactly zero.
 */
final class AmortizationSchedule
{
    /**
     * @param  int  $aprBasisPoints  APR in basis points, e.g. 899 means 8.99%.
     * @return list<array{number:int,due_date:string,payment:Money,principal:Money,interest:Money,balance_after:Money}>
     */
    public static function build(Money $principal, int $aprBasisPoints, int $termMonths, DateTimeImmutable $firstDueDate): array
    {
        if (!$principal->isPositive()) {
            throw new InvalidArgumentException('Principal must be positive.');
        }
        if ($termMonths < 1 || $termMonths > 600) {
            throw new InvalidArgumentException('Term must be between 1 and 600 months.');
        }
        if ($aprBasisPoints < 0 || $aprBasisPoints > 10000) {
            throw new InvalidArgumentException('APR must be between 0 and 10000 basis points.');
        }

        $payment = self::levelPayment($principal->cents, $aprBasisPoints, $termMonths);
        $balance = $principal->cents;
        $rows = [];

        for ($n = 1; $n <= $termMonths; $n++) {
            $interest = intdiv(2 * $balance * $aprBasisPoints + 120000, 240000); // balance * apr / 12, half up
            $isLast = $n === $termMonths;

            if ($isLast) {
                $principalPart = $balance;
                $thisPayment = $balance + $interest;
            } else {
                $thisPayment = $payment;
                $principalPart = $thisPayment - $interest;
                if ($principalPart > $balance) { // never overpay in a short final stretch
                    $principalPart = $balance;
                    $thisPayment = $balance + $interest;
                }
            }

            $balance -= $principalPart;

            $rows[] = [
                'number' => $n,
                'due_date' => self::addMonthsClamped($firstDueDate, $n - 1)->format('Y-m-d'),
                'payment' => Money::cents($thisPayment),
                'principal' => Money::cents($principalPart),
                'interest' => Money::cents($interest),
                'balance_after' => Money::cents($balance),
            ];
        }

        return $rows;
    }

    /** Level monthly payment in cents. A 0% loan splits the principal evenly (rounded up). */
    public static function levelPayment(int $principalCents, int $aprBasisPoints, int $termMonths): int
    {
        if ($aprBasisPoints === 0) {
            return intdiv($principalCents + $termMonths - 1, $termMonths);
        }

        $r = $aprBasisPoints / 120000; // monthly rate as a fraction
        $payment = $principalCents * $r / (1 - (1 + $r) ** -$termMonths);

        return (int) round($payment);
    }

    /** Add months, keeping the day of month where possible and clamping to month end (Jan 31 to Feb 28). */
    public static function addMonthsClamped(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $first = $date->modify('first day of this month')->modify("+{$months} months");
        $lastDay = (int) $first->format('t');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $lastDay));
    }
}
