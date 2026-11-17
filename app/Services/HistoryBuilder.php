<?php

namespace App\Services;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes believable past activity for a new account that ends on an exact balance.
 * Pure: the same inputs and seed always give the same rows. It writes nothing; TransferService::postHistory does.
 */
final class HistoryBuilder
{
    private const MERCHANTS = [
        'groceries' => ['Pine Street Grocery', 'Harvest Market', 'Lakeview Foods'],
        'dining' => ['Bluebird Coffee', 'Corner Cafe', 'Maple Grill', 'Riverside Pizza'],
        'fuel' => ['Corner Fuel', 'Eastside Fuel', 'City Transit'],
        'shopping' => ['Eastside Books', 'Hillcrest Outlet', 'Home and Garden Store'],
        'health' => ['Lakeview Pharmacy', 'Northside Dental'],
        'entertainment' => ['Grand Cinema', 'Music Stream Monthly'],
    ];

    private const BILLS = [['Electric utility', 7], ['Internet service', 5], ['Wireless phone', 4], ['Water and sewer', 3]];

    public const VOLUMES = ['few' => 'A little', 'medium' => 'A medium amount', 'many' => 'A lot'];

    private Randomizer $rng;

    private string $volume = 'medium';

    /** @return list<array{type: TransactionType, direction: string, amount: int, at: CarbonImmutable, description: string}> */
    public static function build(string $kind, CarbonImmutable $from, CarbonImmutable $to, int $target, int $seed, string $volume = 'medium'): array
    {
        $b = new self;
        $b->volume = isset(self::VOLUMES[$volume]) ? $volume : 'medium';
        $b->rng = new Randomizer(new Mt19937($seed));
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        return $kind === 'checking' ? $b->checking($from, $to, $target) : $b->savings($from, $to, $target);
    }

    private function checking(CarbonImmutable $from, CarbonImmutable $to, int $target): array
    {
        $days = $from->diffInDays($to);
        $paydays = [];
        for ($d = $this->rng->getInt(1, min(10, max(1, $days))); $d <= $days; $d += 14) {
            $paydays[] = $from->addDays($d);
        }
        $paydays = $paydays ?: [$from->addDays(min(1, $days))];
        $n = count($paydays);

        $spendShare = $this->rng->getInt(55, 75) / 100;
        $income = (int) round($target / (1 - $spendShare));
        $pay = max(100, intdiv($income, $n));
        $spend = $pay * $n - $target;

        // Spending events, each with a weight; amounts are shared out by weight so the total is exact.
        $events = [];
        $weeks = max(1, intdiv($days, 7));
        $perWeek = ['few' => 1, 'medium' => 3, 'many' => 7][$this->volume];
        for ($i = 0, $k = $weeks * $perWeek + 2; $i < $k; $i++) {
            $cat = $this->rng->pickArrayKeys(self::MERCHANTS, 1)[0];
            $name = self::MERCHANTS[$cat][$this->rng->getInt(0, count(self::MERCHANTS[$cat]) - 1)];
            $w = match ($cat) { 'groceries' => $this->rng->getInt(6, 14), 'dining' => $this->rng->getInt(2, 5), 'fuel' => $this->rng->getInt(4, 7), 'health' => $this->rng->getInt(3, 8), default => $this->rng->getInt(3, 10) };
            $events[] = ['type' => TransactionType::CardPurchase, 'desc' => $name, 'w' => $w * $this->rng->getInt(70, 130), 'at' => $from->addDays($this->rng->getInt(0, $days))];
        }
        for ($m = $from->startOfMonth(); $m <= $to; $m = $m->addMonth()) {
            $bills = match ($this->volume) { 'few' => array_slice(self::BILLS, 0, 2), 'many' => [...self::BILLS, ['Renters insurance', 4], ['Gym and fitness club', 3]], default => self::BILLS };
            foreach ($bills as [$name, $w]) {
                $at = $m->addDays($this->rng->getInt(2, 24));
                if ($at >= $from && $at <= $to) {
                    $events[] = ['type' => TransactionType::BillPayment, 'desc' => $name, 'w' => $w * $this->rng->getInt(90, 110), 'at' => $at];
                }
            }
        }
        for ($d = $this->rng->getInt(2, 9); $d <= $days; $d += ['few' => 30, 'medium' => 14, 'many' => 7][$this->volume]) {
            $events[] = ['type' => TransactionType::Withdrawal, 'desc' => 'ATM withdrawal', 'w' => 5 * $this->rng->getInt(80, 120), 'at' => $from->addDays($d)];
        }

        // Too many events for the money: drop the smallest until each is worth at least $5.
        usort($events, fn ($a, $b) => $a['w'] <=> $b['w']);
        while (count($events) > 1 && $spend / array_sum(array_column($events, 'w')) * $events[0]['w'] < 500) {
            array_shift($events);
        }
        $weights = array_sum(array_column($events, 'w'));
        $left = $spend;
        foreach ($events as $i => $e) {
            $events[$i]['amount'] = (int) floor($spend * $e['w'] / $weights);
            $left -= $events[$i]['amount'];
        }
        $events[count($events) - 1]['amount'] += $left;
        $events = array_values(array_filter($events, fn ($e) => $e['amount'] > 0));

        $rows = [];
        foreach ($paydays as $i => $day) {
            $rows[] = $this->row(TransactionType::Deposit, 'credit', $pay, $day->setTime(6, $this->rng->getInt(0, 59)), 'Direct deposit, payroll');
        }
        foreach ($events as $e) {
            $rows[] = $this->row($e['type'], 'debit', $e['amount'], $e['at']->setTime($this->rng->getInt(8, 20), $this->rng->getInt(0, 59)), $e['desc']);
        }
        usort($rows, fn ($a, $b) => $a['at'] <=> $b['at']);

        // Walk the days. A purchase the balance cannot cover is dropped; what that frees is spent in the last days.
        $balance = 0;
        $kept = [];
        foreach ($rows as $r) {
            $delta = $r['direction'] === 'credit' ? $r['amount'] : -$r['amount'];
            if ($balance + $delta < 0) {
                continue;
            }
            $balance += $delta;
            $kept[] = $r;
        }
        $extra = $balance - $target;
        if ($extra > 0) {
            $pieces = max(1, min(6, intdiv($extra, 2500)));
            $each = intdiv($extra, $pieces);
            for ($i = 0; $i < $pieces; $i++) {
                $amt = $i === $pieces - 1 ? $extra - $each * ($pieces - 1) : $each;
                $kept[] = $this->row(TransactionType::CardPurchase, 'debit', $amt, $to->setTime(14 + $i, $this->rng->getInt(0, 59)), self::MERCHANTS['shopping'][$i % 3]);
            }
        } elseif ($extra < 0) { // cannot happen with dropped debits only; kept for safety
            $kept[] = $this->row(TransactionType::Deposit, 'credit', -$extra, $to->setTime(9, 0), 'Deposit');
        }
        usort($kept, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $kept;
    }

    private function savings(CarbonImmutable $from, CarbonImmutable $to, int $target): array
    {
        $starts = [];
        for ($m = $from->startOfMonth(); $m <= $to; $m = $m->addMonth()) {
            $at = $m->addDays($this->rng->getInt(0, 4));
            if ($this->volume === 'few' && $m->month % 3 !== 0) {
                continue;
            }
            foreach ($this->volume === 'many' ? [$at, $at->addDays(14)] : [$at] as $when) {
                if ($when >= $from && $when <= $to) {
                    $starts[] = $when;
                }
            }
        }
        $starts = $starts ?: [$from];
        $per = max(100, intdiv($target, count($starts)));
        $rows = $this->savingsRows($starts, $per, $from, $to);
        $end = $this->endBalance($rows);
        if ($end > 0) { // scale the deposits so the dividends do not push the total far past the target
            $rows = $this->savingsRows($starts, max(100, (int) round($per * $target / $end)), $from, $to);
            $end = $this->endBalance($rows);
        }
        if ($end < $target) {
            $rows[] = $this->row(TransactionType::Deposit, 'credit', $target - $end, $to->setTime(10, 0), 'Deposit');
        } elseif ($end > $target) {
            $rows[] = $this->row(TransactionType::Withdrawal, 'debit', $end - $target, $to->setTime(10, 0), 'Withdrawal');
        }
        usort($rows, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $rows;
    }

    private function savingsRows(array $starts, int $per, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        foreach ($starts as $at) {
            $rows[] = $this->row(TransactionType::Deposit, 'credit', $per, $at->setTime(9, 30), 'Deposit');
        }
        $balance = 0;
        for ($m = $from->endOfMonth()->startOfDay(); $m < $to; $m = $m->addMonthNoOverflow()->endOfMonth()->startOfDay()) {
            foreach ($rows as $r) {
                if ($r['at'] <= $m->endOfDay() && $r['at']->format('Y-m') === $m->format('Y-m')) {
                    $balance += $r['amount'];
                }
            }
            $dividend = (int) round($balance * 0.0035 / 12);
            if ($dividend > 0) {
                $rows[] = $this->row(TransactionType::Dividend, 'credit', $dividend, $m->setTime(23, 0), 'Dividend');
                $balance += $dividend;
            }
        }

        return $rows;
    }

    private function endBalance(array $rows): int
    {
        return array_sum(array_map(fn ($r) => $r['direction'] === 'credit' ? $r['amount'] : -$r['amount'], $rows));
    }

    private function row(TransactionType $type, string $dir, int $amount, CarbonImmutable $at, string $description): array
    {
        return ['type' => $type, 'direction' => $dir, 'amount' => $amount, 'at' => $at, 'description' => $description];
    }
}
