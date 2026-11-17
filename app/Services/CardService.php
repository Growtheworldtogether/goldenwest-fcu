<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Decision;
use App\Models\PhysicalCardOrder;
use App\Models\ReasonCode;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\CardNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Virtual debit cards: a member requests one, staff decide, an active card can be frozen, limited,
 * cancelled and replaced. Purchases hold money on the account, then settle through TransferService.
 */
final class CardService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public function request(User $member, int $accountId, ?string $nickname): Card
    {
        if ($member->kyc_status !== 'verified') {
            throw ValidationException::withMessages(['account_id' => 'Please verify your identity before you request a card.']);
        }
        $account = $member->accounts()->where('accounts.id', $accountId)->where('accounts.status', 'active')->where('accounts.type', 'standard')->first();
        if (! $account || $account->product->kind !== 'share_draft') {
            throw ValidationException::withMessages(['account_id' => 'Choose one of your checking accounts.']);
        }
        if (Card::where('user_id', $member->id)->whereIn('status', ['requested', 'active', 'frozen'])->exists()) {
            throw ValidationException::withMessages(['account_id' => 'You already have a card or a request in progress.']);
        }

        $card = Card::create(['user_id' => $member->id, 'account_id' => $account->id, 'nickname' => $nickname ?: null, 'status' => 'requested', 'requested_at' => now()]);
        Audit::record('card.requested', $card, null, ['account' => $account->id], $member->id);

        return $card;
    }

    public function decide(Card $card, User $staff, bool $approve, ?string $reasonCode = null, ?string $reasonText = null): Card
    {
        if ($card->status !== 'requested') {
            throw new InvalidArgumentException('This request was already decided.');
        }
        if (! $approve && (! $reasonCode || ! ReasonCode::where('code', $reasonCode)->exists() || trim((string) $reasonText) === '')) {
            throw ValidationException::withMessages(['reason_text' => 'Choose a reason and tell the member why.']);
        }

        return DB::transaction(function () use ($card, $staff, $approve, $reasonCode, $reasonText) {
            Decision::create([
                'decidable_type' => $card->getMorphClass(), 'decidable_id' => $card->id,
                'decision' => $approve ? 'approved' : 'rejected', 'reason_code' => $approve ? null : $reasonCode,
                'reason_text' => $approve ? null : $reasonText, 'decided_by' => $staff->id,
            ]);
            $approve ? $this->issue($card) : $card->update(['status' => 'rejected']);
            $card->update(['decided_by' => $staff->id, 'decided_at' => now()]);
            Audit::record($approve ? 'card.approved' : 'card.rejected', $card, null, ['reason' => $reasonCode], $staff->id);

            return $card->refresh();
        });
    }

    /** Give the card its number, security code and expiry. */
    private function issue(Card $card): void
    {
        do {
            $serial = random_int(100_000_000_000, 999_999_999_999);
        } while (Card::where('serial', $serial)->exists());
        $number = CardNumber::fromSerial($serial);
        $card->update([
            'status' => 'active', 'serial' => $serial, 'last4' => substr($number, -4),
            'number_encrypted' => Crypt::encryptString($number), 'cvv_encrypted' => Crypt::encryptString(str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT)),
            'expiry_month' => (int) now()->format('n'), 'expiry_year' => (int) now()->addYears(3)->format('Y'),
        ]);
    }

    /** @return array{number: string, cvv: string, expiry: string} */
    public function reveal(Card $card, User $by): array
    {
        abort_unless($card->isLive() && $card->number_encrypted, 404);
        Audit::record('card.revealed', $card, null, null, $by->id);

        return ['number' => trim(chunk_split(Crypt::decryptString($card->number_encrypted), 4, ' ')), 'cvv' => Crypt::decryptString($card->cvv_encrypted), 'expiry' => $card->expiry()];
    }

    public function freeze(Card $card, User $by, bool $freeze): void
    {
        if (! $card->isLive()) {
            throw new InvalidArgumentException('This card cannot be changed.');
        }
        $to = $freeze ? 'frozen' : 'active';
        if ($card->status === $to) {
            return;
        }
        $card->update(['status' => $to]);
        Audit::record($freeze ? 'card.frozen' : 'card.unfrozen', $card, null, ['by' => $by->isAdmin() ? 'staff' : 'member'], $by->id);
    }

    public function setLimits(Card $card, User $member, ?int $dailyCents, ?int $purchaseCents): void
    {
        $maxDaily = (int) config('bank.card_daily_limit');
        $maxPurchase = (int) config('bank.card_purchase_limit');
        if ($dailyCents !== null && ($dailyCents < 100 || $dailyCents > $maxDaily)) {
            throw ValidationException::withMessages(['daily_limit' => 'Daily limit must be between $1 and '.Money::cents($maxDaily)->format().'.']);
        }
        if ($purchaseCents !== null && ($purchaseCents < 100 || $purchaseCents > $maxPurchase)) {
            throw ValidationException::withMessages(['purchase_limit' => 'Purchase limit must be between $1 and '.Money::cents($maxPurchase)->format().'.']);
        }
        $old = ['daily' => $card->daily_limit, 'purchase' => $card->purchase_limit];
        $card->update(['daily_limit' => $dailyCents, 'purchase_limit' => $purchaseCents]);
        Audit::record('card.limits_changed', $card, $old, ['daily' => $dailyCents, 'purchase' => $purchaseCents], $member->id);
    }

    public function cancel(Card $card, User $by): void
    {
        if (! $card->isLive()) {
            throw new InvalidArgumentException('This card is already closed.');
        }
        DB::transaction(function () use ($card, $by) {
            foreach ($card->purchases()->where('status', 'authorized')->get() as $p) {
                $this->release($p);
            }
            $card->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            Audit::record('card.cancelled', $card, null, ['by' => $by->isAdmin() ? 'staff' : 'member'], $by->id);
        });
    }

    /** Cancel a card and issue a new one on the same account straight away. */
    public function replace(Card $card, User $member): Card
    {
        if (! $card->isLive()) {
            throw new InvalidArgumentException('This card cannot be replaced.');
        }

        return DB::transaction(function () use ($card, $member) {
            $this->cancel($card, $member);
            $new = Card::create(['user_id' => $member->id, 'account_id' => $card->account_id, 'nickname' => $card->nickname, 'status' => 'requested', 'requested_at' => now(), 'replaces_card_id' => $card->id, 'daily_limit' => $card->daily_limit, 'purchase_limit' => $card->purchase_limit]);
            $this->issue($new);
            $new->update(['decided_at' => now()]);
            Audit::record('card.replaced', $new, ['old_last4' => $card->last4], ['new_last4' => $new->last4], $member->id);

            return $new->refresh();
        });
    }

    /** Order a physical card for an active card. One order at a time; it ships to the address given. */
    public function orderPhysical(Card $card, User $member, array $address): PhysicalCardOrder
    {
        if ($card->user_id !== $member->id || ! $card->isLive()) {
            throw new InvalidArgumentException('Your card must be active before you can order a physical one.');
        }
        if (PhysicalCardOrder::where('card_id', $card->id)->whereIn('status', ['requested', 'shipped'])->exists()) {
            throw new InvalidArgumentException('A physical card is already on its way or being reviewed.');
        }
        $order = PhysicalCardOrder::create($address + ['card_id' => $card->id, 'user_id' => $member->id, 'status' => 'requested']);
        Audit::record('card.physical_requested', $card, null, ['city' => $address['city']], $member->id);

        return $order;
    }

    public function cancelPhysical(PhysicalCardOrder $order, User $member): void
    {
        if ($order->user_id !== $member->id || $order->status !== 'requested') {
            throw new InvalidArgumentException('This order can no longer be cancelled.');
        }
        $order->update(['status' => 'cancelled']);
        Audit::record('card.physical_cancelled', $order->card, null, null, $member->id);
    }

    public function decidePhysical(PhysicalCardOrder $order, User $staff, bool $approve, ?string $reasonCode = null, ?string $reasonText = null): void
    {
        if ($order->status !== 'requested') {
            throw new InvalidArgumentException('This order was already decided.');
        }
        if (! $approve && (! $reasonCode || ! ReasonCode::where('code', $reasonCode)->exists() || trim((string) $reasonText) === '')) {
            throw ValidationException::withMessages(['reason_text' => 'Choose a reason and tell the member why.']);
        }
        DB::transaction(function () use ($order, $staff, $approve, $reasonCode, $reasonText) {
            Decision::create([
                'decidable_type' => $order->getMorphClass(), 'decidable_id' => $order->id,
                'decision' => $approve ? 'approved' : 'rejected', 'reason_code' => $approve ? null : $reasonCode,
                'reason_text' => $approve ? null : $reasonText, 'decided_by' => $staff->id,
            ]);
            $order->update($approve ? ['status' => 'shipped', 'shipped_at' => now()] : ['status' => 'rejected', 'decline_note' => $reasonText]);
            Audit::record($approve ? 'card.physical_shipped' : 'card.physical_rejected', $order->card, null, ['reason' => $reasonCode], $staff->id);
        });
    }

    /** A merchant asks for an authorization. The hold is made, or the attempt is recorded as declined. */
    public function authorize(Card $card, string $merchant, Money $amount): CardTransaction
    {
        $decline = $this->declineReason($card, $amount);
        if ($decline === null) {
            try {
                $this->ledger->holdFunds($card->account_id, $amount);
            } catch (TransferException $e) {
                $decline = $e->reason === 'insufficient_funds' ? 'insufficient_funds' : 'account_restricted';
            }
        }

        $p = CardTransaction::create([
            'card_id' => $card->id, 'merchant' => Str::limit($merchant, 80, ''), 'amount' => $amount->cents,
            'status' => $decline ? 'declined' : 'authorized', 'decline_reason' => $decline, 'authorized_at' => $decline ? null : now(),
        ]);
        Audit::record($decline ? 'card.declined' : 'card.authorized', $card, null, ['merchant' => $p->merchant, 'amount' => $amount->cents, 'reason' => $decline], $card->user_id);

        return $p;
    }

    /** Turn an authorization into a posted purchase. */
    public function settle(CardTransaction $p): CardTransaction
    {
        return DB::transaction(function () use ($p) {
            $p = CardTransaction::whereKey($p->id)->lockForUpdate()->first();
            if ($p->status !== 'authorized') {
                throw new InvalidArgumentException('This purchase is not waiting to be settled.');
            }
            $card = $p->card;
            $this->ledger->releaseFunds($card->account_id, $p->money());
            $tx = $this->ledger->execute(new TransferRequest(
                type: TransactionType::CardPurchase, amount: $p->money(), idempotencyKey: 'card-'.$p->id,
                fromAccountId: $card->account_id, initiatedBy: $card->user_id,
                memo: 'Card ending '.$card->last4, memberDescription: $p->merchant,
            ));
            $p->update(['status' => 'settled', 'settled_at' => now(), 'transaction_id' => $tx->id]);
            Audit::record('card.settled', $card, null, ['merchant' => $p->merchant, 'amount' => $p->amount], $card->user_id);

            return $p;
        });
    }

    /** Let an authorization lapse. The held money goes back to the available balance. */
    public function release(CardTransaction $p): void
    {
        DB::transaction(function () use ($p) {
            $p = CardTransaction::whereKey($p->id)->lockForUpdate()->first();
            if ($p->status !== 'authorized') {
                return;
            }
            $this->ledger->releaseFunds($p->card->account_id, $p->money());
            $p->update(['status' => 'released']);
            Audit::record('card.released', $p->card, null, ['merchant' => $p->merchant, 'amount' => $p->amount], $p->card->user_id);
        });
    }

    /** Authorize and settle in one step, the way an online checkout does. */
    public function pay(Card $card, string $merchant, Money $amount): CardTransaction
    {
        $p = $this->authorize($card, $merchant, $amount);

        return $p->status === 'authorized' ? $this->settle($p) : $p;
    }

    private function declineReason(Card $card, Money $amount): ?string
    {
        if ($card->status === 'frozen') {
            return 'card_frozen';
        }
        if ($card->status !== 'active') {
            return 'card_not_active';
        }
        if ($amount->cents <= 0) {
            return 'invalid_amount';
        }
        if ($amount->greaterThan($card->purchaseLimit())) {
            return 'over_purchase_limit';
        }
        $today = (int) CardTransaction::where('card_id', $card->id)->whereIn('status', ['authorized', 'settled'])->where('created_at', '>=', now()->startOfDay())->sum('amount');
        if ($today + $amount->cents > $card->dailyLimit()->cents) {
            return 'over_daily_limit';
        }

        return null;
    }

    public static function declineText(?string $reason): string
    {
        return [
            'card_frozen' => 'The card is frozen.', 'card_not_active' => 'The card is not active.', 'invalid_amount' => 'The amount is not valid.',
            'over_purchase_limit' => 'Over the purchase limit.', 'over_daily_limit' => 'Over the daily limit.',
            'insufficient_funds' => 'Not enough available money.', 'account_restricted' => 'The account is restricted.',
        ][$reason] ?? 'Declined.';
    }
}
