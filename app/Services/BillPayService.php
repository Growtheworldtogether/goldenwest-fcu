<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BillPayment;
use App\Models\Biller;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Bill pay: billers, scheduled and repeating payments, delivery estimates and review for large amounts. */
final class BillPayService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public function addBiller(User $member, string $name, string $category, string $reference): Biller
    {
        if (Biller::where('user_id', $member->id)->where('status', 'active')->count() >= 25) {
            throw ValidationException::withMessages(['name' => 'You can save up to 25 billers.']);
        }
        $b = Biller::create(['user_id' => $member->id, 'name' => $name, 'category' => $category, 'account_reference' => $reference, 'status' => 'active']);
        Audit::record('biller.added', $b, null, ['name' => $name], $member->id);

        return $b;
    }

    public function removeBiller(Biller $biller, User $member): void
    {
        abort_unless($biller->user_id === $member->id, 404);
        $biller->update(['status' => 'removed']);
        BillPayment::where('biller_id', $biller->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);
        Audit::record('biller.removed', $biller, null, null, $member->id);
    }

    public function schedule(User $member, Biller $biller, Account $from, Money $amount, Carbon $sendDate, string $recurrence): BillPayment
    {
        if ($biller->user_id !== $member->id || $biller->status !== 'active') {
            throw new InvalidArgumentException('Choose one of your saved billers.');
        }
        if (! $member->accounts()->where('accounts.id', $from->id)->where('accounts.status', 'active')->where('accounts.type', 'standard')->exists()) {
            throw ValidationException::withMessages(['account_id' => 'Choose one of your accounts.']);
        }
        if ($amount->cents < 100) {
            throw ValidationException::withMessages(['amount' => 'The amount must be at least $1.']);
        }
        if ($sendDate->lt(today()) || $sendDate->gt(today()->addYear())) {
            throw ValidationException::withMessages(['send_date' => 'Pick a date from today to one year from now.']);
        }
        if (! in_array($recurrence, ['none', 'weekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['recurrence' => 'Choose how often to repeat.']);
        }

        $p = BillPayment::create(['user_id' => $member->id, 'biller_id' => $biller->id, 'account_id' => $from->id, 'amount' => $amount->cents, 'send_date' => $sendDate->toDateString(), 'recurrence' => $recurrence, 'status' => 'scheduled', 'estimated_delivery' => $this->deliveryDate($sendDate)]);
        Audit::record('bill.scheduled', $p, null, ['biller' => $biller->name, 'amount' => $amount->cents, 'send' => $p->send_date->toDateString()], $member->id);
        if ($sendDate->isToday()) {
            $this->send($p);
        }

        return $p->refresh();
    }

    public function cancel(BillPayment $p, User $member): void
    {
        if ($p->user_id !== $member->id || $p->status !== 'scheduled') {
            throw new InvalidArgumentException('This payment can no longer be cancelled.');
        }
        $p->update(['status' => 'cancelled', 'recurrence' => 'none']);
        Audit::record('bill.cancelled', $p, null, null, $member->id);
    }

    /** Business days after the send date. */
    public function deliveryDate(Carbon $send): Carbon
    {
        $d = $send->copy()->startOfDay();
        $left = (int) config('bank.bill_delivery_days');
        while ($left > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $left--;
            }
        }

        return $d;
    }

    /** Send one payment: move the money, or hold it for staff when it is large. */
    public function send(BillPayment $p): void
    {
        if ($p->status !== 'scheduled') {
            return;
        }
        $over = $p->amount > (int) config('bank.bill_review_threshold');
        try {
            $tx = $this->ledger->execute(new TransferRequest(
                type: TransactionType::BillPayment, amount: $p->money(), idempotencyKey: 'bill-'.$p->id, fromAccountId: $p->account_id, initiatedBy: $p->user_id,
                memo: 'Bill pay: '.$p->biller->name.' ('.$p->biller->account_reference.')', memberDescription: 'Bill payment, '.$p->biller->name, requireReview: $over,
            ));
        } catch (TransferException $e) {
            $p->update(['status' => 'failed', 'failure' => $e->getMessage()]);
            Audit::record('bill.failed', $p, null, ['why' => $e->getMessage()], null);

            return;
        }
        $p->update(['transaction_id' => $tx->id, 'status' => $tx->status === TransactionStatus::Completed ? 'processing' : 'review']);
        Audit::record('bill.sent', $p, null, ['status' => $p->status], $p->user_id);
        $this->queueNext($p);
    }

    /** Bring statuses in line with the ledger and the calendar. */
    public function refresh(?int $userId = null): void
    {
        BillPayment::with('transaction')->where('status', 'review')->when($userId, fn ($q) => $q->where('user_id', $userId))->each(function (BillPayment $p) {
            $status = $p->transaction?->status;
            if ($status === TransactionStatus::Completed) {
                $p->update(['status' => 'processing']);
            } elseif (in_array($status, [TransactionStatus::Rejected, TransactionStatus::Failed], true)) {
                $p->update(['status' => 'failed', 'failure' => 'We could not send this payment.']);
            }
        });
        BillPayment::where('status', 'processing')->whereDate('estimated_delivery', '<=', today())->when($userId, fn ($q) => $q->where('user_id', $userId))->update(['status' => 'delivered']);
    }

    /** Daily job. Returns [sent, delivered]. */
    public function processDaily(): array
    {
        $sent = 0;
        BillPayment::with('biller')->where('status', 'scheduled')->whereDate('send_date', '<=', today())->each(function (BillPayment $p) use (&$sent) {
            $this->send($p);
            $sent++;
        });
        $before = BillPayment::where('status', 'delivered')->count();
        $this->refresh();

        return [$sent, BillPayment::where('status', 'delivered')->count() - $before];
    }

    private function queueNext(BillPayment $p): void
    {
        if ($p->recurrence === 'none') {
            return;
        }
        $next = $p->recurrence === 'weekly' ? $p->send_date->copy()->addWeek() : $p->send_date->copy()->addMonthNoOverflow();
        if ($next->lt(today())) {
            $next = today()->addDay();
        }
        BillPayment::create(['user_id' => $p->user_id, 'biller_id' => $p->biller_id, 'account_id' => $p->account_id, 'amount' => $p->amount, 'send_date' => $next->toDateString(), 'recurrence' => $p->recurrence, 'status' => 'scheduled', 'estimated_delivery' => $this->deliveryDate($next)]);
    }
}
