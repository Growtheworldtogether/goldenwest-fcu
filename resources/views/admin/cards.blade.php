@extends('layouts.portal')
@section('title', 'Cards')
@section('band')
<h1>Virtual cards</h1>
<p class="psub">Review requests and manage issued cards.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Filter">
    <a href="{{ route('admin.cards.index') }}" @if ($tab === 'requests') aria-current="page" @endif>Requests</a>
    <a href="{{ route('admin.cards.index', ['tab' => 'physical']) }}" @if ($tab === 'physical') aria-current="page" @endif>Physical card orders</a>
    <a href="{{ route('admin.cards.index', ['tab' => 'all']) }}" @if ($tab === 'all') aria-current="page" @endif>All cards</a>
</nav>
<div class="panel-card flush">
@if ($tab === 'physical')
    @if ($orders->count())
    <table class="dtable"><thead><tr><th>Member</th><th>Ship to</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach ($orders as $o)
        <tr>
            <td><a class="main" href="{{ route('admin.members.show', $o->user) }}">{{ $o->user->name }}</a><small>Card ending {{ $o->card->last4 }} &middot; {{ $o->created_at->format('M j, g:i a') }}</small></td>
            <td>{{ $o->name_on_card }}<small>{{ $o->addressLine() }}</small></td>
            <td><span class="chip {{ ['shipped' => 'completed', 'rejected' => 'rejected'][$o->status] ?? 'pending' }}">{{ ucfirst($o->status) }}</span></td>
            <td class="go" style="min-width:16rem">
                @if ($o->status === 'requested')
                    <form method="post" action="{{ route('admin.cards.physical.decide', $o) }}" style="display:inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-s sm" type="submit">Approve and ship</button></form>
                    <details class="act" style="margin-top:.5rem"><summary>Decline</summary>
                        <form class="sform" method="post" action="{{ route('admin.cards.physical.decide', $o) }}">@csrf<input type="hidden" name="decision" value="reject">
                            <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                            <label>Message to the member<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                            <button type="submit" class="plain">Decline order</button></form>
                    </details>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody></table>
    @else<div class="empty"><b>Nothing here</b>No physical card orders yet.</div>@endif
@elseif ($cards->count())
<div style="overflow-x:auto"><table class="dtable">
    <thead><tr><th>Member</th><th>Account</th><th>Status</th><th>{{ $tab === 'requests' ? 'Requested' : 'Card' }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($cards as $c)
        <tr>
            <td><a class="main" href="{{ route('admin.members.show', $c->user) }}">{{ $c->user->name }}</a><small>#{{ $c->user->member_number }} &middot; ID {{ $c->user->kyc_status }}</small></td>
            <td>{{ $c->account->nickname ?? $c->account->product->name }}<small>{{ $c->account->masked() }}</small></td>
            <td><span class="chip {{ ['active' => 'completed', 'requested' => 'pending', 'frozen' => 'held', 'rejected' => 'rejected'][$c->status] ?? 'pending' }}">{{ ucfirst($c->status) }}</span></td>
            <td>{{ $tab === 'requests' ? $c->requested_at?->format('M j, g:i a') : ($c->last4 ? 'ending '.$c->last4 : 'none') }}</td>
            <td class="go" style="min-width:16rem">
                @if ($c->status === 'requested')
                    <form method="post" action="{{ route('admin.cards.decide', $c) }}" style="display:inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-s sm" type="submit">Approve</button></form>
                    <details class="act" style="margin-top:.5rem"><summary>Decline</summary>
                        <form class="sform" method="post" action="{{ route('admin.cards.decide', $c) }}">@csrf<input type="hidden" name="decision" value="reject">
                            <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                            <label>Message to the member<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                            <button type="submit" class="plain">Decline request</button></form>
                    </details>
                @elseif (in_array($c->status, ['active', 'frozen']))
                    <form method="post" action="{{ route('admin.cards.change', $c) }}" style="display:inline">@csrf<input type="hidden" name="action" value="{{ $c->status === 'frozen' ? 'unfreeze' : 'freeze' }}"><button class="btn-s plain sm" type="submit">{{ $c->status === 'frozen' ? 'Unfreeze' : 'Freeze' }}</button></form>
                    <form method="post" action="{{ route('admin.cards.change', $c) }}" style="display:inline" onsubmit="return confirm('Cancel this card?')">@csrf<input type="hidden" name="action" value="cancel"><button class="btn-s plain sm" type="submit">Cancel</button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@else<div class="empty"><b>Nothing here</b>{{ $tab === 'requests' ? 'No card requests are waiting.' : 'No cards yet.' }}</div>@endif
</div>
@if ($tab !== 'physical' && $cards->hasPages())<p class="pager" style="margin-top:1rem">{{ $cards->links() }}</p>@endif
@endsection
