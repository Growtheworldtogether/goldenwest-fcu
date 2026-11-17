<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\ReasonCode;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TicketReplied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Support tickets. A member opens one (or contests a decision), staff reply and leave internal notes.
 * Statuses: open, awaiting_staff, awaiting_member, resolved, closed. Internal notes never reach the member.
 */
final class TicketService
{
    public function __construct(private SecureUpload $uploads)
    {
    }

    /** @param  array<int, UploadedFile>  $files */
    public function open(User $member, string $category, string $subject, string $body, ?Transaction $tx = null, array $files = []): Ticket
    {
        return DB::transaction(function () use ($member, $category, $subject, $body, $tx, $files) {
            $ticket = Ticket::create([
                'reference' => $this->reference(),
                'user_id' => $member->id,
                'category' => $category,
                'subject' => $subject,
                'status' => 'awaiting_staff',
                'transaction_id' => $tx?->id,
                'account_id' => $tx ? ($tx->to_account_id ?? $tx->from_account_id) : null,
                'last_reply_at' => now(),
            ]);
            $message = TicketMessage::create(['ticket_id' => $ticket->id, 'author_id' => $member->id, 'body' => $body]);
            $this->attach($message, $files);
            Audit::record('ticket.opened', $ticket, null, ['category' => $category, 'transaction' => $tx?->reference], $member->id);

            return $ticket;
        });
    }

    /** @param  array<int, UploadedFile>  $files */
    public function memberReply(Ticket $ticket, User $member, string $body, array $files = []): TicketMessage
    {
        if ($ticket->user_id !== $member->id) {
            throw new InvalidArgumentException('Not your ticket.');
        }
        if ($ticket->status === 'closed') {
            throw new InvalidArgumentException('This request is closed. Please open a new one.');
        }

        return DB::transaction(function () use ($ticket, $member, $body, $files) {
            $message = TicketMessage::create(['ticket_id' => $ticket->id, 'author_id' => $member->id, 'body' => $body]);
            $this->attach($message, $files);
            $was = $ticket->status;
            $ticket->update(['status' => 'awaiting_staff', 'last_reply_at' => now(), 'resolved_at' => null]);
            Audit::record('ticket.replied', $ticket, ['status' => $was], ['status' => 'awaiting_staff', 'by' => 'member'], $member->id);

            return $message;
        });
    }

    /** @param  array<int, UploadedFile>  $files */
    public function staffReply(Ticket $ticket, User $staff, string $body, array $files = []): TicketMessage
    {
        if ($ticket->status === 'closed') {
            throw new InvalidArgumentException('This ticket is closed. Reopen it to reply.');
        }

        $message = DB::transaction(function () use ($ticket, $staff, $body, $files) {
            $message = TicketMessage::create(['ticket_id' => $ticket->id, 'author_id' => $staff->id, 'body' => $body]);
            $this->attach($message, $files);
            $was = $ticket->status;
            $ticket->update(['status' => 'awaiting_member', 'last_reply_at' => now(), 'assigned_to' => $ticket->assigned_to ?? $staff->id, 'resolved_at' => null]);
            Audit::record('ticket.replied', $ticket, ['status' => $was], ['status' => 'awaiting_member', 'by' => 'staff'], $staff->id);

            return $message;
        });

        $ticket->user->notify(new TicketReplied($ticket));

        return $message;
    }

    /** Internal notes are for staff only. They do not change the status and the member is never told. */
    public function note(Ticket $ticket, User $staff, string $body): TicketMessage
    {
        $message = TicketMessage::create(['ticket_id' => $ticket->id, 'author_id' => $staff->id, 'body' => $body, 'is_internal_note' => true]);
        Audit::record('ticket.note_added', $ticket, null, null, $staff->id);

        return $message;
    }

    public function setStatus(Ticket $ticket, User $by, string $status): void
    {
        if (! in_array($status, ['resolved', 'closed', 'awaiting_staff', 'awaiting_member'], true)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        $this->change($ticket, $status, $by);
    }

    /** A member marks their own request resolved. */
    public function memberResolve(Ticket $ticket, User $member): void
    {
        if ($ticket->user_id !== $member->id || $ticket->status === 'closed') {
            throw new InvalidArgumentException('This request cannot be changed.');
        }
        $this->change($ticket, 'resolved', $member);
    }

    private function change(Ticket $ticket, string $status, User $by): void
    {
        $old = $ticket->status;
        if ($old === $status) {
            return;
        }
        $ticket->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? now() : ($status === 'closed' ? $ticket->resolved_at : null),
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
        Audit::record('ticket.status_changed', $ticket, ['status' => $old], ['status' => $status], $by->id);
    }

    public function assign(Ticket $ticket, User $by, ?int $assigneeId): void
    {
        if ($assigneeId !== null) {
            $assignee = User::where('role', 'admin')->find($assigneeId);
            if (! $assignee || ! $assignee->checkPermissionTo('tickets.answer')) {
                throw ValidationException::withMessages(['assigned_to' => 'Choose a staff member who answers tickets.']);
            }
        }
        $old = $ticket->assigned_to;
        if ($old === $assigneeId) {
            return;
        }
        $ticket->update(['assigned_to' => $assigneeId]);
        Audit::record('ticket.assigned', $ticket, ['assigned_to' => $old], ['assigned_to' => $assigneeId], $by->id);
    }

    public function setPriority(Ticket $ticket, User $by, string $priority): void
    {
        if (! isset(config('support.priorities')[$priority])) {
            throw ValidationException::withMessages(['priority' => 'Choose a priority.']);
        }
        $old = $ticket->priority;
        if ($old === $priority) {
            return;
        }
        $ticket->update(['priority' => $priority]);
        Audit::record('ticket.priority_changed', $ticket, ['priority' => $old], ['priority' => $priority], $by->id);
    }

    /** Close resolved tickets that have sat for the set number of days. Returns how many were closed. */
    public function autoClose(): int
    {
        $count = 0;
        Ticket::where('status', 'resolved')->where('resolved_at', '<=', now()->subDays((int) config('support.auto_close_days')))->each(function (Ticket $t) use (&$count) {
            $t->update(['status' => 'closed', 'closed_at' => now()]);
            Audit::record('ticket.status_changed', $t, ['status' => 'resolved'], ['status' => 'closed', 'auto' => true], null);
            $count++;
        });

        return $count;
    }

    /** "Contest this decision" opens one ticket linked to the transaction, or returns the one already open. @return array{0: Ticket, 1: bool} */
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
        $body = trim(($message ?: 'I would like you to review this decision again.')."\n\nDecision: ".ucfirst($tx->status->value === 'held' ? 'on hold' : 'declined').($reason ? ' - '.$reason : '').($decision?->reason_text ? ' ('.$decision->reason_text.')' : ''));

        $ticket = $this->open($member, 'transaction', 'Contest: '.Str::limit($what, 60).' for '.$tx->amount->format(), $body, $tx);
        $ticket->update(['priority' => 'high']);

        return [$ticket, true];
    }

    /** Fill {first_name}, {short_name}, {phone} and {hours} into a canned reply for this member. */
    public static function cannedFor(Ticket $ticket): array
    {
        $first = $ticket->user->first_name ?? $ticket->user->name;

        return collect(config('support.canned'))->map(fn ($c) => [
            'label' => $c['label'],
            'body' => str_replace(['{first_name}', '{short_name}', '{phone}', '{hours}'], [$first, brand('short_name'), brand('support_phone'), brand('support_hours')], $c['body']),
        ])->all();
    }

    /** @param  array<int, UploadedFile>  $files */
    private function attach(TicketMessage $message, array $files): void
    {
        foreach (array_slice(array_filter($files), 0, (int) config('support.max_attachments')) as $file) {
            $stored = $this->uploads->store($file, 'tickets', 'files', allowPdf: true);
            TicketAttachment::create(['ticket_id' => $message->ticket_id, 'message_id' => $message->id, 'path' => $stored['path'], 'name' => $stored['name'], 'mime' => $stored['mime'], 'size' => $stored['size']]);
        }
    }

    private function reference(): string
    {
        do {
            $ref = 'TK'.random_int(10_000_000, 99_999_999);
        } while (Ticket::where('reference', $ref)->exists());

        return $ref;
    }
}
