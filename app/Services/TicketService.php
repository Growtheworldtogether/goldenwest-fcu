<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\ReasonCode;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Support tickets. Tickets grow in M5 (replies, inbox); here a member can contest a decision, which opens a linked one. */
final class TicketService
{
    /** @return array{0: Ticket, 1: bool} the ticket and whether it was just created */
    public function contest(User $member, Transaction $tx, ?string $message): array
    {
        if (! in_array($tx->status, [TransactionStatus::Rejected, TransactionStatus::Held], true)) {
            throw new InvalidArgumentException('Only a declined or held transaction can be contested.');
        }

        $open = Ticket::where('user_id', $member->id)->where('transaction_id', $tx->id)->where('category', 'transaction')->whereNotIn('status', ['resolved', 'closed'])->first();
        if ($open) {
            return [$open, false];
        }

        $decision = $tx->decisions()->latest('id')->first();
        $reason = $decision?->reason_code ? ReasonCode::where('code', $decision->reason_code)->value('label') : null;
        $what = $tx->member_description ?? str($tx->type->value)->replace('_', ' ')->title()->toString();

        $ticket = Ticket::create([
            'reference' => 'TK'.random_int(10_000_000, 99_999_999),
            'user_id' => $member->id,
            'category' => 'transaction',
            'subject' => 'Contest: '.Str::limit($what, 60).' for '.$tx->amount->format(),
            'status' => 'awaiting_staff',
            'transaction_id' => $tx->id,
            'account_id' => $tx->to_account_id ?? $tx->from_account_id,
            'last_reply_at' => now(),
        ]);
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $member->id,
            'body' => trim(($message ?: 'I would like you to review this decision again.')."\n\nDecision: ".ucfirst($tx->status->value === 'held' ? 'on hold' : 'declined').($reason ? ' - '.$reason : '').($decision?->reason_text ? ' ('.$decision->reason_text.')' : '')),
        ]);
        Audit::record('ticket.opened', $ticket, null, ['transaction' => $tx->reference, 'category' => 'transaction'], $member->id);

        return [$ticket, true];
    }
}
