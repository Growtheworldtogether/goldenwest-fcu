<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TicketController extends Controller
{
    private const TABS = ['needs' => 'Needs a reply', 'mine' => 'Assigned to me', 'waiting' => 'Waiting for member', 'done' => 'Resolved and closed', 'all' => 'All'];

    public function index(Request $request)
    {
        Gate::authorize('staff.answerInquiries');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'needs';
        $q = trim((string) $request->query('q', ''));

        $tickets = Ticket::with(['user', 'assignee'])
            ->when($tab === 'needs', fn ($b) => $b->whereIn('status', ['open', 'awaiting_staff']))
            ->when($tab === 'mine', fn ($b) => $b->where('assigned_to', $request->user()->id)->whereNotIn('status', ['resolved', 'closed']))
            ->when($tab === 'waiting', fn ($b) => $b->where('status', 'awaiting_member'))
            ->when($tab === 'done', fn ($b) => $b->whereIn('status', ['resolved', 'closed']))
            ->when(in_array($request->query('category'), array_keys(config('support.categories')), true), fn ($b) => $b->where('category', $request->query('category')))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('reference', 'like', '%'.$q.'%')->orWhere('subject', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('member_number', $q))))
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderBy('last_reply_at')
            ->paginate(25)->withQueryString();

        return view('admin.tickets', ['tickets' => $tickets, 'tab' => $tab, 'tabs' => self::TABS, 'q' => $q, 'category' => $request->query('category')]);
    }

    public function show(Ticket $ticket)
    {
        Gate::authorize('staff.answerInquiries');
        $ticket->load(['user', 'assignee', 'transaction.fromAccount', 'transaction.toAccount', 'account.product', 'messages.author', 'messages.attachments']);

        return view('admin.ticket', [
            'ticket' => $ticket,
            'canned' => TicketService::cannedFor($ticket),
            'staff' => User::where('role', 'admin')->orderBy('name')->get()->filter(fn ($u) => $u->checkPermissionTo('tickets.answer')),
            'canDecide' => $ticket->transaction ? Gate::allows('staff.decideTransaction', $ticket->transaction) : false,
        ]);
    }

    public function reply(Request $request, Ticket $ticket, TicketService $tickets)
    {
        Gate::authorize('staff.answerInquiries');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:'.config('support.max_attachments')],
            'files.*' => ['file'],
        ], ['body.required' => 'Write a message first.']);

        try {
            if ($request->boolean('internal')) {
                $tickets->note($ticket, $request->user(), $data['body']);
                $toast = ['type' => 'info', 'message' => 'Internal note saved. The member cannot see it.'];
            } else {
                $tickets->staffReply($ticket, $request->user(), $data['body'], $request->file('files', []));
                $toast = ['type' => 'ok', 'message' => 'Reply sent. The member was emailed.'];
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.show', $ticket)->with('toast', $toast);
    }

    public function update(Request $request, Ticket $ticket, TicketService $tickets)
    {
        Gate::authorize('staff.answerInquiries');
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['resolved', 'closed', 'awaiting_staff'])],
            'priority' => ['nullable', Rule::in(array_keys(config('support.priorities')))],
            'assigned_to' => ['nullable'],
        ]);

        if ($request->has('assigned_to')) {
            $tickets->assign($ticket, $request->user(), $data['assigned_to'] ? (int) $data['assigned_to'] : null);
        }
        if (! empty($data['priority'])) {
            $tickets->setPriority($ticket, $request->user(), $data['priority']);
        }
        if (! empty($data['status'])) {
            $tickets->setStatus($ticket, $request->user(), $data['status']);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Ticket updated.']);
    }
}
