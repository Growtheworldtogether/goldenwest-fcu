<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        return view('portal.tickets', [
            'tickets' => Ticket::where('user_id', $request->user()->id)->orderByRaw("case when status in ('resolved','closed') then 1 else 0 end")->orderByDesc('last_reply_at')->paginate(20),
        ]);
    }

    public function create(Request $request)
    {
        $accountIds = $request->user()->accounts()->pluck('accounts.id');

        return view('portal.ticket-new', [
            'transactions' => Transaction::whereIn('from_account_id', $accountIds)->orWhereIn('to_account_id', $accountIds)->latest('id')->limit(15)->get(),
            'selected' => $request->query('transaction'),
        ]);
    }

    public function store(Request $request, TicketService $tickets)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('support.categories')))],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'transaction' => ['nullable', 'string', 'size:26'],
            'files' => ['nullable', 'array', 'max:'.config('support.max_attachments')],
            'files.*' => ['file'],
        ], ['subject.required' => 'Give your request a short title.', 'body.required' => 'Tell us what you need help with.', 'files.max' => 'You can attach up to '.config('support.max_attachments').' files.']);

        $tx = null;
        if (! empty($data['transaction'])) {
            $tx = Transaction::where('reference', $data['transaction'])->firstOrFail();
            $this->authorize('view', $tx);
        }

        $ticket = $tickets->open($request->user(), $data['category'], $data['subject'], $data['body'], $tx, $request->file('files', []));

        return redirect()->route('tickets.show', $ticket->reference)->with('toast', ['type' => 'ok', 'message' => 'Request '.$ticket->reference.' sent. We will reply here and send you an email when we do.']);
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return view('portal.ticket', ['ticket' => $ticket->load(['transaction', 'memberMessages.author', 'memberMessages.attachments'])]);
    }

    public function reply(Request $request, Ticket $ticket, TicketService $tickets)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.config('support.max_attachments')],
            'files.*' => ['file'],
        ], ['body.required' => 'Write your message first.']);

        try {
            $tickets->memberReply($ticket, $request->user(), $data['body'], $request->file('files', []));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket->reference)->with('toast', ['type' => 'ok', 'message' => 'Your message was sent.']);
    }

    public function resolve(Request $request, Ticket $ticket, TicketService $tickets)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        try {
            $tickets->memberResolve($ticket, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket->reference)->with('toast', ['type' => 'ok', 'message' => 'Marked as resolved. Reply any time if you need more help.']);
    }
}
