<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Console\Command;

class VerifyLedger extends Command
{
    protected $signature = 'ledger:verify';

    protected $description = 'Recompute every account balance from the ledger and report any difference.';

    public function handle(): int
    {
        $problems = 0;

        foreach (Account::orderBy('id')->cursor() as $account) {
            $credits = (int) LedgerEntry::where('account_id', $account->id)->where('direction', 'credit')->sum('amount');
            $debits = (int) LedgerEntry::where('account_id', $account->id)->where('direction', 'debit')->sum('amount');

            $open = Transaction::where('from_account_id', $account->id)
                ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Held->value]);
            $held = (int) $open->sum('amount') + (int) $open->sum('fee');

            $expected = ['balance' => $credits - $debits, 'held' => $held, 'available' => $credits - $debits - $held];
            $actual = ['balance' => $account->balance->cents, 'held' => $account->held_balance->cents, 'available' => $account->available_balance->cents];

            if ($expected !== $actual) {
                $problems++;
                $this->error("Account {$account->masked()} (id {$account->id}) differs. Ledger: ".json_encode($expected).' Stored: '.json_encode($actual));
            }
        }

        $this->{$problems ? 'error' : 'info'}($problems ? "{$problems} account(s) do not match the ledger." : 'Every account matches the ledger.');

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
