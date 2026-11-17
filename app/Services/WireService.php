<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WireRecipient;
use App\Models\WireTransfer;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use App\Support\Iban;
use App\Support\Routing;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Outgoing wires. Only members with the `wires` feature can send one. The member types the recipient's details
 * for each wire (or picks a recipient they saved from an earlier wire). Every wire waits for staff review, the
 * amount and fee are set aside at once, and a wire sent after the cutoff goes out the next business day.
 */
final class WireService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public static function fee(bool $international = false): Money
    {
        return $international
            ? Money::cents((int) Setting::value('wire_fee_intl_cents', config('bank.wire_fee_international')))
            : Money::cents((int) Setting::value('wire_fee_cents', config('bank.wire_fee')));
    }

    /** Why a destination is refused, or null when it is fine to send there. */
    public static function countryProblem(?string $country): ?string
    {
        $country = strtoupper((string) $country);
        if (in_array($country, config('wires.blocked'), true)) {
            return 'We cannot send wires to '.(config('wires.countries')[$country] ?? 'that country').'.';
        }
        if (! isset(config('wires.countries')[$country])) {
            return 'Choose a country from the list.';
        }

        return null;
    }

    /** Field errors for an international recipient, keyed by form field. @return array<string, string> */
    public static function internationalProblems(array $to): array
    {
        $errors = [];
        $country = strtoupper((string) ($to['country'] ?? ''));
        if ($problem = self::countryProblem($country)) {
            return ['country' => $problem];
        }
        $swift = strtoupper(preg_replace('/\s+/', '', (string) ($to['swift'] ?? '')));
        if (! preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $swift)) {
            $errors['swift'] = 'A SWIFT/BIC code has 8 or 11 letters and numbers, for example DEUTDEFF.';
        } elseif (substr($swift, 4, 2) !== $country) {
            $errors['swift'] = 'This SWIFT/BIC code belongs to a bank in '.(config('wires.countries')[substr($swift, 4, 2)] ?? substr($swift, 4, 2)).', not '.config('wires.countries')[$country].'.';
        }
        $account = (string) ($to['account_number'] ?? '');
        if (in_array($country, config('wires.iban'), true)) {
            if (! Iban::valid($account, $country)) {
                $errors['account_number'] = 'Banks in '.config('wires.countries')[$country].' use an IBAN. That one does not look right. Check it with the recipient.';
            }
        } elseif (! preg_match('/^[A-Za-z0-9\-]{4,34}$/', preg_replace('/\s+/', '', $account))) {
            $errors['account_number'] = 'Enter the recipient\'s account number (letters and numbers only).';
        }

        return $errors;
    }

    public static function cutoff(): string
    {
        return Setting::value('wire_cutoff', config('bank.wire_cutoff'));
    }

    /** The business day this wire is sent: today before the cutoff on a weekday, otherwise the next weekday. */
    public static function processOn(?CarbonInterface $now = null): Carbon
    {
        $local = Carbon::instance($now ?? now())->timezone(config('bank.wire_timezone'));
        [$h, $m] = array_map('intval', explode(':', self::cutoff()));
        $day = $local->copy()->startOfDay();

        if ($local->isWeekday() && $local->lt($day->copy()->setTime($h, $m))) {
            return $day;
        }

        return $day->addWeekday();
    }

    /**
     * @param  array{name: string, address: string, bank_name: string, routing_number?: ?string, account_number: string, kind?: string, country?: ?string, swift?: ?string}  $to
     */
    public function send(User $member, Account $from, array $to, Money $amount, string $purpose, ?string $message, string $token, bool $saveRecipient = false, ?int $impersonatorId = null): Transaction
    {
        if (! Features::enabled($member, 'wires')) {
            throw TransferException::because('feature_off', 'Wire transfers are not turned on for your membership. Call Customer Care to ask about them.');
        }
        if ($member->kyc_status !== 'verified') {
            throw TransferException::because('kyc_required', 'Verify your identity before you send a wire.');
        }
        $international = ($to['kind'] ?? 'domestic') === 'international';
        if ($international) {
            if (! Features::enabled($member, 'wires_international')) {
                throw TransferException::because('feature_off', 'International wires are not turned on for your membership. Call Customer Care to ask about them.');
            }
            $to['country'] = strtoupper((string) $to['country']);
            $to['swift'] = strtoupper(preg_replace('/\s+/', '', (string) $to['swift']));
            $to['account_number'] = Iban::normalize($to['account_number']);
            $to['routing_number'] = null;
            if ($errors = self::internationalProblems($to)) {
                throw ValidationException::withMessages($errors);
            }
        } elseif (! Routing::valid((string) ($to['routing_number'] ?? ''))) {
            throw ValidationException::withMessages(['routing_number' => 'That routing number is not valid. Check the nine digits with the recipient\'s bank.']);
        }
        $fee = self::fee($international);

        $on = self::processOn();
        $tx = $this->ledger->execute(new TransferRequest(
            type: TransactionType::WireOut,
            amount: $amount,
            idempotencyKey: 'wire:'.$member->id.':'.$token,
            fromAccountId: $from->id,
            fee: $fee,
            initiatedBy: $member->id,
            impersonatorId: $impersonatorId,
            memo: $purpose,
            memberDescription: ($international ? 'International wire to ' : 'Wire to ').$to['name'],
            effectiveDate: $on->toDateString(),
        ));

        if (WireTransfer::where('transaction_id', $tx->id)->exists()) {
            return $tx; // the form was sent twice
        }

        $recipientId = null;
        if ($saveRecipient) {
            $recipientId = $this->remember($member, $to)->id;
        }

        $wire = WireTransfer::create([
            'user_id' => $member->id, 'transaction_id' => $tx->id, 'recipient_id' => $recipientId,
            'recipient_name' => $to['name'], 'recipient_address' => $to['address'], 'bank_name' => $to['bank_name'],
            'routing_number' => $to['routing_number'] ?? null, 'account_number' => $to['account_number'], 'last4' => substr($to['account_number'], -4),
            'kind' => $international ? 'international' : 'domestic', 'country' => $to['country'] ?? null, 'swift' => $to['swift'] ?? null,
            'purpose' => $purpose, 'message' => $message, 'process_on' => $on->toDateString(),
        ]);
        Audit::record('wire.requested', $wire, null, ['amount' => $amount->cents, 'fee' => $fee->cents, 'kind' => $international ? 'international' : 'domestic', 'country' => $to['country'] ?? null, 'to' => $to['name'], 'last4' => $wire->last4, 'process_on' => $on->toDateString(), 'first_to_this_account' => $wire->earlierWiresToSameAccount() === 0], $member->id, $impersonatorId);

        return $tx;
    }

    /** Keep a recipient for next time, once per bank account. */
    private function remember(User $member, array $to): WireRecipient
    {
        $international = ($to['kind'] ?? 'domestic') === 'international';
        $existing = WireRecipient::where('user_id', $member->id)->where('status', 'active')
            ->when($international, fn ($q) => $q->where('swift', $to['swift']), fn ($q) => $q->where('routing_number', $to['routing_number']))->get();
        foreach ($existing as $r) {
            if ($r->account_number === $to['account_number']) {
                return $r;
            }
        }
        $recipient = WireRecipient::create([
            'user_id' => $member->id, 'name' => $to['name'], 'address' => $to['address'], 'bank_name' => $to['bank_name'],
            'routing_number' => $to['routing_number'] ?? null, 'account_number' => $to['account_number'], 'last4' => substr($to['account_number'], -4),
            'kind' => $international ? 'international' : 'domestic', 'country' => $to['country'] ?? null, 'swift' => $to['swift'] ?? null,
        ]);
        Audit::record('wire.recipient_saved', $recipient, null, ['name' => $recipient->name, 'last4' => $recipient->last4], $member->id);

        return $recipient;
    }

    public function forget(WireRecipient $recipient, User $by): void
    {
        $recipient->update(['status' => 'removed']);
        Audit::record('wire.recipient_removed', $recipient, ['status' => 'active'], ['status' => 'removed'], $by->id);
    }
}
