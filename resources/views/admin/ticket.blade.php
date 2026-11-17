@extends('layouts.portal')
@section('title', 'Ticket '.$ticket->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('admin.tickets.index') }}">&larr; Tickets</a></p>
<div class="pband-row">
    <div>
        <h1>{{ $ticket->subject }}</h1>
        <p class="psub">{{ $ticket->reference }} &middot; {{ $ticket->categoryLabel() }} &middot; opened {{ $ticket->created_at->format('M j, g:i a') }} &middot; <span class="tag">{{ $ticket->statusLabel() }}</span></p>
    </div>
</div>
@endsection
@section('content')
<div class="cols tcols">
<div>
    <div class="panel-card tthread">
        @foreach ($ticket->messages as $m)
            @php($staffMsg = $m->author->isAdmin())
            <div class="tmsg {{ $m->is_internal_note ? 'note' : ($staffMsg ? 'staff' : 'mine') }}">
                <p class="fine" style="margin:0 0 .25rem"><strong>{{ $m->author->name }}</strong> @if ($m->is_internal_note)<span class="chip held">Internal note, the member cannot see this</span>@elseif ($staffMsg)<span class="fine">replied to the member</span>@endif &middot; {{ $m->created_at->format('M j, g:i a') }}</p>
                <p style="margin:0;white-space:pre-line">{{ $m->body }}</p>
                @include('portal._attachments', ['message' => $m])
            </div>
        @endforeach
    </div>

    @if ($ticket->status !== 'closed')
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Reply</h3>
        <form class="sform" method="post" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" id="replyform">@csrf
            <label>Quick reply<select id="canned"><option value="">Choose a saved reply</option>@foreach ($canned as $k => $c)<option value="{{ $k }}">{{ $c['label'] }}</option>@endforeach</select></label>
            <label>Message<textarea name="body" id="body" rows="5" maxlength="5000" required>{{ old('body') }}</textarea>@error('body')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Attach files (optional)<input type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf"></label>
            <label class="chk" style="display:flex;gap:.55rem;align-items:center;font-weight:400"><input type="checkbox" name="internal" value="1" id="internal"><span>Internal note only. The member is not told and cannot see it.</span></label>
            <button type="submit" class="gold" id="sendbtn">Send reply to member</button>
        </form>
    </div>
    <script>
    (function () {
        var canned = @json($canned), pick = document.getElementById('canned'), body = document.getElementById('body'),
            internal = document.getElementById('internal'), btn = document.getElementById('sendbtn');
        pick.addEventListener('change', function () { if (canned[pick.value]) { body.value = canned[pick.value].body; body.focus(); } });
        internal.addEventListener('change', function () { btn.textContent = internal.checked ? 'Save internal note' : 'Send reply to member'; });
    })();
    </script>
    @else
    <div class="notice-bar" style="margin-top:1.25rem"><p>This ticket is closed. Reopen it from the panel on the right to reply.</p></div>
    @endif
</div>

<aside>
    <div class="panel-card">
        <h3>Member</h3>
        <p style="margin:0"><a class="plink" href="{{ route('admin.members.show', $ticket->user) }}">{{ $ticket->user->name }}</a><br><span class="fine">#{{ $ticket->user->member_number }} &middot; {{ $ticket->user->email }}</span></p>
    </div>

    @if ($ticket->transaction)
    @php($tx = $ticket->transaction)
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Linked transaction</h3>
        <dl class="kv">
            <dt>What</dt><dd>{{ $tx->member_description ?? str($tx->type->value)->replace('_', ' ')->title() }}</dd>
            <dt>Amount</dt><dd>{{ $tx->amount->format() }}</dd>
            <dt>Status</dt><dd><span class="chip {{ $tx->status->value }}">{{ ucfirst($tx->status->value) }}</span></dd>
            <dt>Date</dt><dd>{{ $tx->effective_date->format('M j, Y') }}</dd>
        </dl>
        <p style="margin:.6rem 0 0"><a class="btn-s plain" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $canDecide && in_array($tx->status->value, ['pending', 'held']) ? 'Review and decide' : 'Open transaction' }}</a></p>
    </div>
    @endif

    @if ($ticket->account)
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Linked account</h3>
        <p style="margin:0">{{ $ticket->account->nickname }} {{ $ticket->account->masked() }}<br><span class="fine">{{ $ticket->account->product->name }} &middot; balance {{ $ticket->account->balance->format() }}</span></p>
    </div>
    @endif

    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Handle this ticket</h3>
        <form class="sform" method="post" action="{{ route('admin.tickets.update', $ticket) }}">@csrf @method('PUT')
            <label>Assigned to<select name="assigned_to"><option value="">Nobody</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select>@error('assigned_to')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Priority<select name="priority">@foreach (config('support.priorities') as $k => $l)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $l }}</option>@endforeach</select></label>
            <button type="submit" class="plain">Save</button>
        </form>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem">
            @if (in_array($ticket->status, ['resolved', 'closed']))
                <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">@csrf @method('PUT')<input type="hidden" name="status" value="awaiting_staff"><button type="submit" class="btn-s plain sm">Reopen</button></form>
            @else
                <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">@csrf @method('PUT')<input type="hidden" name="status" value="resolved"><button type="submit" class="btn-s plain sm">Mark resolved</button></form>
            @endif
            @if ($ticket->status !== 'closed')
                <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">@csrf @method('PUT')<input type="hidden" name="status" value="closed"><button type="submit" class="btn-s plain sm">Close</button></form>
            @endif
        </div>
        @if ($ticket->status === 'resolved')<p class="fine" style="margin-bottom:0">Closes by itself {{ config('support.auto_close_days') }} days after it was resolved.</p>@endif
    </div>
</aside>
</div>
@endsection
