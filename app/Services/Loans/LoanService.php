<?php

namespace App\Services\Loans;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Decision;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Notifications\MemberNotice;
use App\Models\ReasonCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\Features;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Loan applications, decisions, disbursement, repayment, autopay and late fees.
 * Every cent that moves goes through TransferService; schedules come from AmortizationSchedule.
 */
final class LoanService
{
    public function __construct(private TransferService $ledger)
    {
    }

    // ------------------------------------------------------------ applying

    public function apply(User $member, LoanProduct $product, array $d): LoanApplication
    {
        if (! Features::enabled($member, 'loans')) {
            abort(404);
        }
        if ($member->kyc_status !== 'verified') {
            throw ValidationException::withMessages(['amount' => 'Please verify your identity before you apply for a loan.']);
        }
        if (! $product->active || $d['amount'] < $product->min_amount || $d['amount'] > $product->max_amount) {
            throw ValidationException::withMessages(['amount' => 'Choose an amount from '.$product->minAmount()->format().' to '.$product->maxAmount()->format().'.']);
        }
        if (! in_array((int) $d['term_months'], $product->terms, true)) {
            throw ValidationException::withMessages(['term_months' => 'Choose one of the available terms.']);
        }
        if ($product->isBusiness() && ! $member->businessProfile()->exists()) {
            throw ValidationException::withMessages(['amount' => 'Business loans are for members with a business. Add your business first.']);
        }
        $account = $member->accounts()->with('product')->where('accounts.id', $d['deposit_account_id'])->where('accounts.status', 'active')->where('accounts.type', 'standard')->first();
        if (! $account || $account->product->isBusiness() !== $product->isBusiness()) {
            throw ValidationException::withMessages(['deposit_account_id' => $product->isBusiness() ? 'Choose one of your business accounts to receive the money.' : 'Choose one of your personal accounts to receive the money.']);
        }
        if ($product->type === 'bequip' && (trim((string) ($d['collateral'] ?? '')) === '' || (int) ($d['collateral_value'] ?? 0) <= 0)) {
            throw ValidationException::withMessages(['collateral' => 'Describe the equipment and enter what it costs.']);
        }
        if (LoanApplication::where('user_id', $member->id)->whereIn('status', ['submitted', 'in_review', 'needs_info'])->exists()) {
            throw ValidationException::withMessages(['amount' => 'You already have an application in progress. Please wait for it to be decided.']);
        }

        $app = LoanApplication::create([
            'reference' => $this->reference('LA', LoanApplication::class), 'user_id' => $member->id, 'loan_product_id' => $product->id,
            'amount' => $d['amount'], 'term_months' => $d['term_months'], 'purpose' => $d['purpose'], 'annual_income' => $d['annual_income'],
            'employment' => $d['employment'], 'deposit_account_id' => $account->id, 'status' => 'submitted',
            'vehicle_year' => $d['vehicle_year'] ?? null, 'vehicle_make' => $d['vehicle_make'] ?? null, 'vehicle_model' => $d['vehicle_model'] ?? null, 'vehicle_value' => $d['vehicle_value'] ?? null,
            'collateral' => $d['collateral'] ?? null, 'collateral_value' => $d['collateral_value'] ?? null,
        ]);
        Audit::record('loan.applied', $app, null, ['product' => $product->code, 'amount' => $app->amount, 'term' => $app->term_months], $member->id);

        return $app;
    }

    public function reply(LoanApplication $app, User $member, string $text): void
    {
        if ($app->user_id !== $member->id || $app->status !== 'needs_info') {
            throw new InvalidArgumentException('This application is not waiting on you.');
        }
        $app->update(['member_reply' => $text, 'status' => 'in_review']);
        Audit::record('loan.info_provided', $app, null, null, $member->id);
    }

    public function withdraw(LoanApplication $app, User $member): void
    {
        if ($app->user_id !== $member->id || ! $app->isOpen()) {
            throw new InvalidArgumentException('This application can no longer be withdrawn.');
        }
        $app->update(['status' => 'withdrawn']);
        Audit::record('loan.withdrawn', $app, null, null, $member->id);
    }

    // ----------------------------------------------------------- deciding

    public function startReview(LoanApplication $app, User $staff): void
    {
        if ($app->status !== 'submitted') {
            return;
        }
        $app->update(['status' => 'in_review']);
        Audit::record('loan.review_started', $app, null, null, $staff->id);
    }

    public function requestInfo(LoanApplication $app, User $staff, string $question): void
    {
        $this->assertOpen($app);
        if (trim($question) === '') {
            throw ValidationException::withMessages(['info_request' => 'Tell the member what you need.']);
        }
        $app->update(['status' => 'needs_info', 'info_request' => $question, 'member_reply' => null]);
        Audit::record('loan.info_requested', $app, null, null, $staff->id);
        MemberNotice::send($app->user, 'We need more information for loan application '.$app->reference, ['We have a question about your loan application '.$app->reference.'.', 'Sign in to read it and reply. We cannot move forward until you do.'], 'Open the application', route('borrow.application', $app->reference));
    }

    public function reject(LoanApplication $app, User $staff, string $reasonCode, string $reasonText): void
    {
        $this->assertOpen($app);
        if (! ReasonCode::where('code', $reasonCode)->exists() || trim($reasonText) === '') {
            throw ValidationException::withMessages(['reason_text' => 'Choose a reason and tell the member why.']);
        }
        DB::transaction(function () use ($app, $staff, $reasonCode, $reasonText) {
            Decision::create(['decidable_type' => $app->getMorphClass(), 'decidable_id' => $app->id, 'decision' => 'rejected', 'reason_code' => $reasonCode, 'reason_text' => $reasonText, 'decided_by' => $staff->id]);
            $app->update(['status' => 'rejected', 'decided_by' => $staff->id, 'decided_at' => now()]);
            Audit::record('loan.rejected', $app, null, ['reason' => $reasonCode], $staff->id);
        });
        MemberNotice::send($app->user, 'Update on loan application '.$app->reference, ['We made a decision on your loan application '.$app->reference.'.', 'Sign in to see it and the reason.'], 'See the decision', route('borrow.application', $app->reference));
    }

    /** Approve at an APR the staff member sets, then pay the money into the member's account. */
    public function approve(LoanApplication $app, User $staff, int $aprBps, ?int $amountCents = null): Loan
    {
        $this->assertOpen($app);
        $product = $app->product;
        $amount = $amountCents ?? $app->amount;
        if ($aprBps < $product->apr_min_bps || $aprBps > $product->apr_max_bps) {
            throw ValidationException::withMessages(['apr' => 'The rate must be between '.$product->aprRange().'.']);
        }
        if ($amount < $product->min_amount || $amount > $app->amount) {
            throw ValidationException::withMessages(['amount' => 'The approved amount cannot be more than the member asked for.']);
        }

        $loan = DB::transaction(function () use ($app, $staff, $aprBps, $amount, $product) {
            Decision::create(['decidable_type' => $app->getMorphClass(), 'decidable_id' => $app->id, 'decision' => 'approved', 'decided_by' => $staff->id]);
            $app->update(['status' => 'approved', 'approved_apr_bps' => $aprBps, 'decided_by' => $staff->id, 'decided_at' => now()]);

            $revolving = $product->revolving;
            $loan = Loan::create([
                'reference' => $this->reference('LN', Loan::class), 'user_id' => $app->user_id, 'loan_application_id' => $app->id, 'loan_product_id' => $product->id,
                'revolving' => $revolving, 'account_id' => $app->deposit_account_id, 'principal' => $amount, 'balance' => $revolving ? 0 : $amount, 'apr_bps' => $aprBps, 'term_months' => $app->term_months,
                'status' => 'active', 'first_due_date' => today()->addDays((int) config('bank.loan_first_due_days')), 'next_due_date' => today()->addDays((int) config('bank.loan_first_due_days')),
                'disbursed_at' => now(),
            ]);
            if ($revolving) {
                // A line of credit opens with nothing owed. The member draws on it when they need the money.
                $loan->update(['next_due_date' => null, 'last_billed_on' => today()]);
            } else {
                $this->writeSchedule($loan, Money::cents($amount), $aprBps, $app->term_months, $loan->first_due_date->toDateTimeImmutable());
                $this->ledger->execute(new TransferRequest(
                    type: TransactionType::LoanDisbursement, amount: Money::cents($amount), idempotencyKey: 'loan-disburse-'.$loan->id,
                    toAccountId: $app->deposit_account_id, initiatedBy: $staff->id, memo: 'Loan '.$loan->reference, memberDescription: $product->name.' funds',
                ));
            }
            Audit::record('loan.approved', $app, null, ['loan' => $loan->reference, 'apr_bps' => $aprBps, 'amount' => $amount], $staff->id);

            return $loan;
        });
        MemberNotice::send($app->user, 'Your loan application '.$app->reference.' was approved', $app->product->revolving
            ? ['Good news: your application '.$app->reference.' was approved and your line of credit is open.', 'Sign in to see your limit and rate, and to draw funds when you need them.']
            : ['Good news: your loan application '.$app->reference.' was approved and the funds were added to your account.', 'Sign in to see your rate, payment schedule and first due date.'], $app->product->revolving ? 'See your line of credit' : 'See your loan', route('borrow.show', $loan->reference));

        return $loan;
    }

    // ---------------------------------------------------------- repayment

    /** Pay the oldest unpaid installment in full, including any late fee. */
    public function payNext(Loan $loan, Account $from, ?int $by = null): LoanInstallment
    {
        return DB::transaction(function () use ($loan, $from, $by) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            $this->assertOpenLoan($loan);
            $inst = $loan->schedule()->where('status', 'due')->first();
            if (! $inst) {
                throw new InvalidArgumentException('There is nothing left to pay.');
            }
            $tx = $this->ledger->execute(new TransferRequest(
                type: TransactionType::LoanPayment, amount: $inst->amountDue(), idempotencyKey: 'loan-pay-'.$inst->id,
                fromAccountId: $from->id, initiatedBy: $by ?? $loan->user_id, memo: 'Loan '.$loan->reference.' payment '.$inst->installment, memberDescription: 'Loan payment, '.$loan->product->name,
            ));
            $inst->update(['status' => 'paid', 'paid_at' => now(), 'transaction_id' => $tx->id]);
            $next = $loan->schedule()->where('status', 'due')->first();
            if ($loan->revolving) {
                $loan->update(['balance' => max(0, $loan->balance - $inst->principal), 'next_due_date' => $next?->due_date, 'status' => 'active']);
            } else {
                $loan->update(['balance' => $inst->balance_after, 'next_due_date' => $next?->due_date, 'status' => $next ? 'active' : 'paid_off', 'paid_off_at' => $next ? null : now()]);
            }
            $this->refreshDelinquency($loan);
            Audit::record('loan.payment', $loan, null, ['installment' => $inst->installment, 'amount' => $inst->amountDue()->cents], $by ?? $loan->user_id);

            return $inst;
        });
    }

    /** Put extra money toward principal. The remaining payments are recalculated over the remaining months. */
    public function payExtra(Loan $loan, Account $from, Money $amount): Loan
    {
        return DB::transaction(function () use ($loan, $from, $amount) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            $this->assertOpenLoan($loan);
            if ($loan->schedule()->where('status', 'due')->where('late_fee', '>', 0)->exists()) {
                throw new InvalidArgumentException('Please pay the past-due payment first.');
            }
            if ($amount->cents <= 0 || $amount->cents >= $loan->balance) {
                throw new InvalidArgumentException('An extra payment must be more than $0 and less than what you still owe. To close the loan, use pay off.');
            }
            $this->ledger->execute(new TransferRequest(
                type: TransactionType::LoanPayment, amount: $amount, idempotencyKey: 'loan-extra-'.$loan->id.'-'.now()->format('uv').'-'.random_int(1000, 9999),
                fromAccountId: $from->id, initiatedBy: $loan->user_id, memo: 'Loan '.$loan->reference.' extra principal', memberDescription: 'Extra payment, '.$loan->product->name,
            ));

            if ($loan->revolving) {
                $loan->update(['balance' => $loan->balance - $amount->cents]);
                Audit::record('loan.extra_payment', $loan, ['balance' => $loan->balance + $amount->cents], ['balance' => $loan->balance], $loan->user_id);

                return $loan->refresh();
            }
            $unpaid = $loan->schedule()->where('status', 'due')->get();
            $first = $unpaid->first();
            $newBalance = $loan->balance - $amount->cents;
            $unpaid->each->delete();
            $rows = AmortizationSchedule::build(Money::cents($newBalance), $loan->apr_bps, $unpaid->count(), $first->due_date->toDateTimeImmutable());
            $this->insertRows($loan, $rows, $first->installment);
            $loan->update(['balance' => $newBalance]);
            Audit::record('loan.extra_payment', $loan, ['balance' => $newBalance + $amount->cents], ['balance' => $newBalance], $loan->user_id);

            return $loan->refresh();
        });
    }

    /** Interest owed up to the given day, on top of the balance and any late fees. */
    public function payoffAmount(Loan $loan, ?Carbon $on = null): Money
    {
        $on = ($on ?? today())->copy()->startOfDay();
        $lastPaid = $loan->schedule()->where('status', 'paid')->latest('installment')->first();
        $since = $loan->revolving
            ? ($loan->last_billed_on ?? $loan->disbursed_at)->copy()->startOfDay()
            : ($lastPaid ? $lastPaid->due_date->copy()->startOfDay() : $loan->disbursed_at->copy()->startOfDay());
        $days = max(0, (int) $since->diffInDays($on));
        $interest = (int) round($loan->balance * $loan->apr_bps / 10000 * $days / 365);
        $fees = (int) $loan->schedule()->where('status', 'due')->sum('late_fee');

        return Money::cents($loan->balance + $interest + $fees);
    }

    public function payOff(Loan $loan, Account $from): Loan
    {
        return DB::transaction(function () use ($loan, $from) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            $this->assertOpenLoan($loan);
            $amount = $this->payoffAmount($loan);
            $this->ledger->execute(new TransferRequest(
                type: TransactionType::LoanPayment, amount: $amount, idempotencyKey: 'loan-payoff-'.$loan->id,
                fromAccountId: $from->id, initiatedBy: $loan->user_id, memo: 'Loan '.$loan->reference.' payoff', memberDescription: 'Loan payoff, '.$loan->product->name,
            ));
            $loan->schedule()->where('status', 'due')->update(['status' => 'paid', 'paid_at' => now()]);
            // Paying a line of credit down to zero does not close it. The member can draw again.
            $loan->update($loan->revolving ? ['balance' => 0, 'status' => 'active', 'next_due_date' => null, 'last_billed_on' => today()] : ['balance' => 0, 'status' => 'paid_off', 'next_due_date' => null, 'paid_off_at' => now()]);
            Audit::record('loan.paid_off', $loan, null, ['amount' => $amount->cents], $loan->user_id);

            return $loan->refresh();
        });
    }

    public function setAutopay(Loan $loan, User $member, ?int $accountId): void
    {
        if ($loan->user_id !== $member->id || ! $loan->isOpen()) {
            throw new InvalidArgumentException('Autopay cannot be changed for this loan.');
        }
        if ($accountId !== null && ! $member->accounts()->where('accounts.id', $accountId)->where('accounts.status', 'active')->where('accounts.type', 'standard')->exists()) {
            throw ValidationException::withMessages(['account_id' => 'Choose one of your accounts.']);
        }
        $loan->update(['autopay_account_id' => $accountId]);
        Audit::record($accountId ? 'loan.autopay_on' : 'loan.autopay_off', $loan, null, ['account' => $accountId], $member->id);
    }

    /**
     * A member is no longer a holder of this account. Their loan autopay and any open application that point at it
     * move to another of their accounts (checking first), or autopay is switched off and the application is
     * withdrawn when they have no other account. Returns what changed so the member can be told.
     *
     * @return array{autopay_moved: array<int, string>, autopay_off: array<int, string>, applications_moved: array<int, string>, applications_withdrawn: array<int, string>, new_account: ?Account}
     */
    public function moveOffAccount(User $member, Account $account, ?User $actor = null): array
    {
        $fallback = $member->accounts()->with('product')->where('accounts.id', '!=', $account->id)->where('accounts.status', 'active')->where('accounts.type', 'standard')->get()
            ->sortBy(fn ($a) => ['share_draft' => 0, 'share_savings' => 1][$a->product->kind] ?? 2)->first();
        $out = ['autopay_moved' => [], 'autopay_off' => [], 'applications_moved' => [], 'applications_withdrawn' => [], 'new_account' => $fallback];
        $by = $actor?->id ?? $member->id;

        foreach (Loan::where('user_id', $member->id)->where('autopay_account_id', $account->id)->whereIn('status', ['active', 'delinquent'])->get() as $loan) {
            $loan->update(['autopay_account_id' => $fallback?->id]);
            Audit::record($fallback ? 'loan.autopay_moved' : 'loan.autopay_off', $loan, ['account' => $account->id], ['account' => $fallback?->id, 'why' => 'holder_removed'], $by);
            $out[$fallback ? 'autopay_moved' : 'autopay_off'][] = $loan->reference;
        }
        foreach (LoanApplication::where('user_id', $member->id)->where('deposit_account_id', $account->id)->whereIn('status', ['submitted', 'in_review', 'needs_info'])->get() as $app) {
            if ($fallback) {
                $app->update(['deposit_account_id' => $fallback->id]);
                Audit::record('loan.deposit_account_moved', $app, ['account' => $account->id], ['account' => $fallback->id, 'why' => 'holder_removed'], $by);
                $out['applications_moved'][] = $app->reference;
            } else {
                $app->update(['status' => 'withdrawn']);
                Audit::record('loan.withdrawn', $app, null, ['why' => 'holder_removed'], $by);
                $out['applications_withdrawn'][] = $app->reference;
            }
        }

        return $out;
    }

    // ------------------------------------------------- line of credit

    /** Take money from a line of credit into one of the member's business accounts. */
    public function draw(Loan $loan, User $member, Money $amount, int $accountId): Loan
    {
        return DB::transaction(function () use ($loan, $member, $amount, $accountId) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->first();
            if ($loan->user_id !== $member->id || ! $loan->revolving || ! $loan->isOpen()) {
                throw new InvalidArgumentException('You cannot draw on this account.');
            }
            if ($loan->status === 'delinquent' || $loan->schedule()->where('status', 'due')->where('late_fee', '>', 0)->exists()) {
                throw new InvalidArgumentException('Please pay the past-due amount before you draw again.');
            }
            if ($amount->cents <= 0) {
                throw new InvalidArgumentException('Enter an amount to draw.');
            }
            if ($amount->cents > $loan->principal - $loan->balance) {
                throw new InvalidArgumentException('You can draw up to '.$loan->availableCredit()->format().'.');
            }
            $to = $member->accounts()->with('product')->where('accounts.id', $accountId)->where('accounts.status', 'active')->where('accounts.type', 'standard')->first();
            if (! $to || ! $to->product->isBusiness()) {
                throw new InvalidArgumentException('Choose one of your business accounts to receive the money.');
            }
            $this->ledger->execute(new TransferRequest(
                type: TransactionType::LoanDisbursement, amount: $amount, idempotencyKey: 'loan-draw-'.$loan->id.'-'.now()->format('uv').'-'.random_int(1000, 9999),
                toAccountId: $to->id, initiatedBy: $member->id, memo: 'Line of credit '.$loan->reference.' draw', memberDescription: 'Line of credit draw',
            ));
            $loan->update(['balance' => $loan->balance + $amount->cents, 'last_billed_on' => $loan->last_billed_on ?? today()]);
            Audit::record('loan.drawn', $loan, ['balance' => $loan->balance - $amount->cents], ['balance' => $loan->balance], $member->id);

            return $loan->refresh();
        });
    }

    /**
     * Once a month a line of credit with a balance gets a bill: a month of interest plus 1% of the balance
     * (never less than $25, never more than what is owed). Returns how many bills were made.
     */
    public function billLines(): int
    {
        $made = 0;
        foreach (Loan::where('revolving', true)->whereIn('status', ['active', 'delinquent'])->where('balance', '>', 0)->get() as $loan) {
            if ($loan->schedule()->where('status', 'due')->exists() || ! $loan->last_billed_on || $loan->last_billed_on->copy()->addMonth()->gt(today())) {
                continue;
            }
            $interest = (int) round($loan->balance * $loan->apr_bps / 10000 / 12);
            $principal = min($loan->balance, max((int) round($loan->balance * 0.01), 2_500 - $interest, 0));
            $due = today()->addDays((int) config('bank.loan_first_due_days'));
            LoanInstallment::create([
                'loan_id' => $loan->id, 'installment' => (int) $loan->schedule()->max('installment') + 1, 'due_date' => $due,
                'principal' => $principal, 'interest' => $interest, 'balance_after' => $loan->balance - $principal,
            ]);
            $loan->update(['last_billed_on' => today(), 'next_due_date' => $due]);
            Audit::record('loan.line_billed', $loan, null, ['interest' => $interest, 'principal' => $principal], null);
            $made++;
        }

        return $made;
    }

    // ------------------------------------------------------------ nightly

    /** Run once a day: autopay what is due, add late fees after the grace period, flag delinquent loans. @return array{paid:int,failed:int,fees:int} */
    public function processDaily(): array
    {
        $out = ['paid' => 0, 'failed' => 0, 'fees' => 0];
        $this->billLines();

        foreach (Loan::whereIn('status', ['active', 'delinquent'])->whereNotNull('autopay_account_id')->get() as $loan) {
            while (($inst = $loan->schedule()->where('status', 'due')->first()) && $inst->due_date->lte(today())) {
                try {
                    $this->payNext($loan, $loan->autopayAccount);
                    $out['paid']++;
                    $loan->refresh();
                } catch (TransferException|InvalidArgumentException $e) {
                    $out['failed']++;
                    Audit::record('loan.autopay_failed', $loan, null, ['installment' => $inst->installment, 'why' => $e->getMessage()], null);
                    break;
                }
            }
        }

        $cutoff = today()->subDays((int) config('bank.loan_grace_days'));
        $late = LoanInstallment::where('status', 'due')->where('late_fee', 0)->where('due_date', '<', $cutoff)->whereHas('loan', fn ($q) => $q->whereIn('status', ['active', 'delinquent']))->get();
        foreach ($late as $inst) {
            $inst->update(['late_fee' => (int) config('bank.loan_late_fee')]);
            Audit::record('loan.late_fee', $inst->loan, null, ['installment' => $inst->installment, 'fee' => $inst->late_fee], null);
            $out['fees']++;
        }

        foreach (Loan::whereIn('status', ['active', 'delinquent'])->get() as $loan) {
            $this->refreshDelinquency($loan);
        }

        return $out;
    }

    private function refreshDelinquency(Loan $loan): void
    {
        if (! $loan->isOpen()) {
            return;
        }
        $oldest = $loan->schedule()->where('status', 'due')->first();
        $delinquent = $oldest && $oldest->due_date->lt(today()->subDays((int) config('bank.loan_delinquent_days')));
        $status = $delinquent ? 'delinquent' : 'active';
        if ($loan->status !== $status) {
            $loan->update(['status' => $status]);
            Audit::record($delinquent ? 'loan.delinquent' : 'loan.current', $loan, null, null, null);
        }
    }

    // ----------------------------------------------------------- helpers

    private function writeSchedule(Loan $loan, Money $principal, int $apr, int $term, DateTimeImmutable $first): void
    {
        $this->insertRows($loan, AmortizationSchedule::build($principal, $apr, $term, $first), 1);
    }

    private function insertRows(Loan $loan, array $rows, int $startNumber): void
    {
        foreach ($rows as $i => $r) {
            LoanInstallment::create([
                'loan_id' => $loan->id, 'installment' => $startNumber + $i, 'due_date' => $r['due_date'],
                'principal' => $r['principal']->cents, 'interest' => $r['interest']->cents, 'balance_after' => $r['balance_after']->cents,
            ]);
        }
    }

    private function assertOpen(LoanApplication $app): void
    {
        if (! $app->isOpen()) {
            throw new InvalidArgumentException('This application was already decided.');
        }
    }

    private function assertOpenLoan(Loan $loan): void
    {
        if (! $loan->isOpen()) {
            throw new InvalidArgumentException('This loan is closed.');
        }
    }

    private function reference(string $prefix, string $model): string
    {
        do {
            $ref = $prefix.random_int(10_000_000, 99_999_999);
        } while ($model::where('reference', $ref)->exists());

        return $ref;
    }
}
