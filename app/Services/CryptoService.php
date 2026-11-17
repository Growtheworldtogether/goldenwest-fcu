<?php

namespace App\Services;

use App\Models\CryptoAddress;
use App\Models\CryptoAsset;
use App\Models\CryptoLedgerEntry;
use App\Models\CryptoTransaction;
use App\Models\CryptoWallet;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Simulated crypto. Wallets, a separate append-only ledger and the same decision flow as cash.
 * Nothing real is held, traded or sent: the "transaction hash" is random text.
 */
class CryptoService
{
    public function wallet(User $user, CryptoAsset $asset): CryptoWallet
    {
        return CryptoWallet::firstOrCreate(['user_id' => $user->id, 'asset_id' => $asset->id], ['balance' => 0, 'held' => 0]);
    }

    public function activeAddress(User $user, CryptoAsset $asset): ?CryptoAddress
    {
        return CryptoAddress::where('user_id', $user->id)->where('asset_id', $asset->id)->where('status', 'active')->latest('id')->first();
    }

    public static function addressProblem(CryptoAsset $asset, string $address): ?string
    {
        $rule = config("crypto.networks.{$asset->network}");
        if ($rule && ! preg_match($rule['pattern'], $address)) {
            return "That is not a valid {$asset->network} address. It looks like this: {$rule['example']}.";
        }

        return null;
    }

    /** The member asks for a deposit address. Staff set it. */
    public function requestAddress(User $user, CryptoAsset $asset): CryptoAddress
    {
        $this->assertOpen($user, $asset);
        $existing = CryptoAddress::where('user_id', $user->id)->where('asset_id', $asset->id)->whereIn('status', ['requested', 'active'])->latest('id')->first();
        if ($existing) {
            return $existing;
        }
        $row = CryptoAddress::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'network' => $asset->network, 'status' => 'requested']);
        Audit::record('crypto.address_requested', $row, null, ['asset' => $asset->symbol], $user->id);

        return $row;
    }

    public function assignAddress(User $member, CryptoAsset $asset, string $address, ?string $memo, User $staff): CryptoAddress
    {
        $address = trim($address);
        if ($problem = self::addressProblem($asset, $address)) {
            throw new InvalidArgumentException($problem);
        }

        return DB::transaction(function () use ($member, $asset, $address, $memo, $staff) {
            CryptoAddress::where('user_id', $member->id)->where('asset_id', $asset->id)->where('status', 'active')->update(['status' => 'retired']);
            $row = CryptoAddress::where('user_id', $member->id)->where('asset_id', $asset->id)->where('status', 'requested')->latest('id')->first()
                ?? new CryptoAddress(['user_id' => $member->id, 'asset_id' => $asset->id, 'network' => $asset->network]);
            $row->fill(['address' => $address, 'memo' => $memo ?: null, 'added_by' => $staff->id, 'status' => 'active'])->save();
            Audit::record('crypto.address_assigned', $row, null, ['asset' => $asset->symbol, 'network' => $asset->network, 'address' => $address], $staff->id);

            return $row;
        });
    }

    /** The member says they sent funds to their address. Staff confirm the simulated deposit. */
    public function declareFunding(User $user, CryptoAsset $asset, int $amount, ?string $note, string $token, ?int $impersonatorId = null): CryptoTransaction
    {
        $this->assertOpen($user, $asset);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount you sent.');
        }
        if ($existing = CryptoTransaction::where('idempotency_key', 'fund-'.$token)->first()) {
            return $existing;
        }
        if (! $this->activeAddress($user, $asset)) {
            throw new InvalidArgumentException('Your deposit address is not ready yet.');
        }
        if (CryptoTransaction::where('user_id', $user->id)->where('type', 'fund')->where('status', 'pending')->count() >= config('crypto.max_pending_deposits')) {
            throw new InvalidArgumentException('You already have several deposits waiting for confirmation. Please wait for those first.');
        }
        $tx = CryptoTransaction::create([
            'reference' => $this->reference(), 'user_id' => $user->id, 'asset_id' => $asset->id, 'type' => 'fund', 'amount' => $amount,
            'description' => $note ? Str::limit($note, 140, '') : null, 'status' => 'pending', 'idempotency_key' => 'fund-'.$token, 'impersonator_id' => $impersonatorId,
        ]);
        Audit::record('crypto.deposit_declared', $tx, null, ['amount' => $amount, 'asset' => $asset->symbol], $user->id, $impersonatorId);

        return $tx;
    }

    /** Staff confirm a deposit the member declared. The amount may be corrected to what actually arrived. */
    public function confirmFunding(CryptoTransaction $tx, User $staff, ?int $amount = null): CryptoTransaction
    {
        return DB::transaction(function () use ($tx, $staff, $amount) {
            $tx = CryptoTransaction::lockForUpdate()->findOrFail($tx->id);
            $this->assertStaffMayDecide($tx, $staff);
            if ($tx->type !== 'fund' || $tx->status !== 'pending') {
                throw new InvalidArgumentException('This deposit was already handled.');
            }
            $old = $tx->amount;
            $tx->update(['amount' => $amount ?: $tx->amount, 'status' => 'completed', 'tx_hash' => $this->hash($tx->asset), 'decided_by' => $staff->id, 'decided_at' => now()]);
            $this->credit($tx, $tx->amount);
            $this->decision($tx, 'approved', $staff);
            Audit::record('crypto.deposit_confirmed', $tx, ['amount' => $old], ['amount' => $tx->amount], $staff->id);

            return $tx->refresh();
        });
    }

    public function rejectFunding(CryptoTransaction $tx, User $staff, ?string $reasonCode, ?string $reasonText): CryptoTransaction
    {
        return DB::transaction(function () use ($tx, $staff, $reasonCode, $reasonText) {
            $tx = CryptoTransaction::lockForUpdate()->findOrFail($tx->id);
            $this->assertStaffMayDecide($tx, $staff);
            if ($tx->type !== 'fund' || $tx->status !== 'pending') {
                throw new InvalidArgumentException('This deposit was already handled.');
            }
            $tx->update(['status' => 'rejected', 'decided_by' => $staff->id, 'decided_at' => now()]);
            $this->decision($tx, 'rejected', $staff, $reasonCode, $reasonText);
            Audit::record('crypto.deposit_rejected', $tx, null, ['reason' => $reasonCode], $staff->id);

            return $tx->refresh();
        });
    }

    /** Staff record a deposit that arrived, without the member declaring it first. */
    public function recordFunding(User $member, CryptoAsset $asset, int $amount, User $staff, ?string $note = null): CryptoTransaction
    {
        $this->assertOpen($member, $asset);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount that arrived.');
        }
        if ($member->is($staff)) {
            throw new InvalidArgumentException('You cannot fund your own wallet.');
        }
        if (! $this->activeAddress($member, $asset)) {
            throw new InvalidArgumentException('Add a deposit address for this asset first.');
        }

        return DB::transaction(function () use ($member, $asset, $amount, $staff, $note) {
            $tx = CryptoTransaction::create([
                'reference' => $this->reference(), 'user_id' => $member->id, 'asset_id' => $asset->id, 'type' => 'fund', 'amount' => $amount,
                'description' => $note ? Str::limit($note, 140, '') : null, 'status' => 'completed', 'tx_hash' => $this->hash($asset), 'decided_by' => $staff->id, 'decided_at' => now(),
            ]);
            $this->credit($tx, $amount);
            $this->decision($tx, 'approved', $staff);
            Audit::record('crypto.deposit_recorded', $tx, null, ['amount' => $amount, 'asset' => $asset->symbol], $staff->id);

            return $tx;
        });
    }

    /** A send request. The amount and the network fee move to held, and the request waits for staff. */
    public function send(User $user, CryptoAsset $asset, string $address, ?string $memo, int $amount, string $token, ?int $impersonatorId = null): CryptoTransaction
    {
        $this->assertOpen($user, $asset);
        if ($existing = CryptoTransaction::where('idempotency_key', 'send-'.$token)->first()) {
            return $existing;
        }
        $address = trim($address);
        if ($problem = self::addressProblem($asset, $address)) {
            throw new InvalidArgumentException($problem);
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter an amount to send.');
        }
        if (CryptoAddress::where('user_id', $user->id)->where('address', $address)->exists()) {
            throw new InvalidArgumentException('That is your own deposit address. Enter the address you want to send to.');
        }
        if (CryptoTransaction::where('user_id', $user->id)->where('type', 'send')->whereIn('status', ['pending', 'held'])->count() >= config('crypto.max_pending_sends')) {
            throw new InvalidArgumentException('You have several sends waiting for review. Please wait for those first.');
        }

        return DB::transaction(function () use ($user, $asset, $address, $memo, $amount, $token, $impersonatorId) {
            $wallet = CryptoWallet::lockForUpdate()->find($this->wallet($user, $asset)->id);
            $total = $amount + $asset->network_fee;
            if ($wallet->available() < $total) {
                throw new InvalidArgumentException('Your available balance is '.$asset->format($wallet->available()).'. This send needs '.$asset->format($total).' including the network fee of '.$asset->format($asset->network_fee).'.');
            }
            $wallet->increment('held', $total);
            $tx = CryptoTransaction::create([
                'reference' => $this->reference(), 'user_id' => $user->id, 'asset_id' => $asset->id, 'type' => 'send', 'amount' => $amount, 'fee' => $asset->network_fee,
                'address' => $address, 'memo' => $memo ?: null, 'status' => 'pending', 'idempotency_key' => 'send-'.$token, 'impersonator_id' => $impersonatorId,
            ]);
            Audit::record('crypto.send_requested', $tx, null, ['amount' => $amount, 'fee' => $asset->network_fee, 'asset' => $asset->symbol, 'to' => $address], $user->id, $impersonatorId);

            return $tx;
        });
    }

    /** The member takes back a send that nobody has started reviewing. */
    public function cancel(CryptoTransaction $tx, User $member): CryptoTransaction
    {
        return DB::transaction(function () use ($tx, $member) {
            $tx = CryptoTransaction::lockForUpdate()->findOrFail($tx->id);
            if ($tx->user_id !== $member->id || $tx->type !== 'send' || $tx->status !== 'pending') {
                throw new InvalidArgumentException('This send can no longer be cancelled.');
            }
            CryptoWallet::where('user_id', $tx->user_id)->where('asset_id', $tx->asset_id)->decrement('held', $tx->total());
            $tx->update(['status' => 'cancelled']);
            Audit::record('crypto.send_cancelled', $tx, null, null, $member->id);

            return $tx->refresh();
        });
    }

    /** Staff approve, reject or hold a send. Rejecting releases the held amount. */
    public function decideSend(CryptoTransaction $tx, User $staff, string $action, ?string $reasonCode = null, ?string $reasonText = null): CryptoTransaction
    {
        if (! in_array($action, ['approve', 'reject', 'hold'], true)) {
            throw new InvalidArgumentException('Unknown action.');
        }
        if ($action !== 'approve' && ! $reasonText) {
            throw new InvalidArgumentException('Give the member a reason.');
        }

        return DB::transaction(function () use ($tx, $staff, $action, $reasonCode, $reasonText) {
            $tx = CryptoTransaction::with('asset')->lockForUpdate()->findOrFail($tx->id);
            $this->assertStaffMayDecide($tx, $staff);
            if ($tx->type !== 'send' || ! in_array($tx->status, ['pending', 'held'], true)) {
                throw new InvalidArgumentException('This send was already handled.');
            }
            if ($action === 'hold' && $tx->status === 'held') {
                throw new InvalidArgumentException('This send is already on hold.');
            }
            $wallet = CryptoWallet::lockForUpdate()->where('user_id', $tx->user_id)->where('asset_id', $tx->asset_id)->firstOrFail();

            if ($action === 'approve') {
                $wallet->decrement('held', $tx->total());
                $tx->update(['status' => 'completed', 'tx_hash' => $this->hash($tx->asset), 'decided_by' => $staff->id, 'decided_at' => now()]);
                $this->debit($tx, $wallet->refresh(), $tx->total());
                $this->decision($tx, 'approved', $staff);
            } elseif ($action === 'reject') {
                $wallet->decrement('held', $tx->total());
                $tx->update(['status' => 'rejected', 'decided_by' => $staff->id, 'decided_at' => now()]);
                $this->decision($tx, 'rejected', $staff, $reasonCode, $reasonText);
            } else {
                $tx->update(['status' => 'held', 'decided_by' => $staff->id, 'decided_at' => now()]);
                $this->decision($tx, 'held', $staff, $reasonCode, $reasonText);
            }
            Audit::record('crypto.send_'.['approve' => 'approved', 'reject' => 'rejected', 'hold' => 'held'][$action], $tx, null, ['reason' => $reasonCode, 'amount' => $tx->amount, 'asset' => $tx->asset->symbol], $staff->id);

            return $tx->refresh();
        });
    }

    /** A signed correction to a wallet, always with a description. A wallet cannot drop below what is held. */
    public function adjust(User $member, CryptoAsset $asset, int $signed, string $description, string $reasonCode, User $staff): CryptoTransaction
    {
        if ($signed === 0) {
            throw new InvalidArgumentException('Enter an amount.');
        }
        if ($member->is($staff)) {
            throw new InvalidArgumentException('You cannot adjust your own wallet.');
        }

        return DB::transaction(function () use ($member, $asset, $signed, $description, $reasonCode, $staff) {
            $wallet = CryptoWallet::lockForUpdate()->find($this->wallet($member, $asset)->id);
            if ($signed < 0 && $wallet->available() < -$signed) {
                throw new InvalidArgumentException('That is more than the available balance of '.$asset->format($wallet->available()).'.');
            }
            $tx = CryptoTransaction::create([
                'reference' => $this->reference(), 'user_id' => $member->id, 'asset_id' => $asset->id, 'type' => 'adjustment', 'amount' => abs($signed),
                'description' => Str::limit($description, 140, ''), 'status' => 'completed', 'decided_by' => $staff->id, 'decided_at' => now(),
            ]);
            $signed > 0 ? $this->credit($tx, $signed) : $this->debit($tx, $wallet, -$signed);
            $this->decision($tx, 'approved', $staff, $reasonCode, $description);
            Audit::record('crypto.adjusted', $tx, null, ['amount' => $signed, 'asset' => $asset->symbol, 'reason' => $reasonCode], $staff->id);

            return $tx;
        });
    }

    private function credit(CryptoTransaction $tx, int $amount): void
    {
        $wallet = CryptoWallet::lockForUpdate()->find($this->wallet($tx->user, $tx->asset)->id);
        $wallet->increment('balance', $amount);
        CryptoLedgerEntry::create(['crypto_transaction_id' => $tx->id, 'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => $amount, 'balance_after' => $wallet->refresh()->balance]);
    }

    private function debit(CryptoTransaction $tx, CryptoWallet $wallet, int $amount): void
    {
        $wallet->decrement('balance', $amount);
        CryptoLedgerEntry::create(['crypto_transaction_id' => $tx->id, 'wallet_id' => $wallet->id, 'direction' => 'debit', 'amount' => $amount, 'balance_after' => $wallet->refresh()->balance]);
    }

    private function decision(CryptoTransaction $tx, string $decision, User $staff, ?string $code = null, ?string $text = null): void
    {
        Decision::create([
            'decidable_type' => $tx->getMorphClass(), 'decidable_id' => $tx->id, 'decision' => $decision,
            'reason_code' => $code, 'reason_text' => $text, 'decided_by' => $staff->id,
        ]);
    }

    /** Staff cannot decide on their own wallet, or on a request they started while acting as the member. */
    private function assertStaffMayDecide(CryptoTransaction $tx, User $staff): void
    {
        if ($tx->user_id === $staff->id || $tx->impersonator_id === $staff->id) {
            throw new InvalidArgumentException('You cannot decide on a request you made yourself. Ask a colleague.');
        }
    }

    private function assertOpen(User $user, CryptoAsset $asset): void
    {
        if (! Features::enabled($user, 'crypto')) {
            throw new InvalidArgumentException('Crypto is not turned on for this member.');
        }
        if ($user->status !== 'active' || ($user->locked_until && $user->locked_until->isFuture())) {
            throw new InvalidArgumentException('This member\'s profile is locked or inactive right now.');
        }
        if (! $asset->enabled) {
            throw new InvalidArgumentException($asset->symbol.' is not available right now.');
        }
    }

    private function reference(): string
    {
        do {
            $ref = 'CR'.random_int(10_000_000, 99_999_999);
        } while (CryptoTransaction::where('reference', $ref)->exists());

        return $ref;
    }

    /** Random text in the shape of a transaction hash. It points at nothing. */
    private function hash(CryptoAsset $asset): string
    {
        return ($asset->network === 'Ethereum' ? '0x' : '').bin2hex(random_bytes(32));
    }
}
