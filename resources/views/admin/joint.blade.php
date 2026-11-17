@extends('layouts.portal')
@section('title', 'Joint accounts')
@section('band')
<h1>Joint accounts</h1>
<p class="psub">Approve new joint holders and handle removals the holder has not agreed to.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Filter">
    <a href="{{ route('admin.joint.index') }}" @if ($tab === 'review') aria-current="page" @endif>To approve @if ($counts['review']) ({{ $counts['review'] }}) @endif</a>
    <a href="{{ route('admin.joint.index', ['tab' => 'consent']) }}" @if ($tab === 'consent') aria-current="page" @endif>Removals waiting @if ($counts['consent']) ({{ $counts['consent'] }}) @endif</a>
    <a href="{{ route('admin.joint.index', ['tab' => 'all']) }}" @if ($tab === 'all') aria-current="page" @endif>All requests</a>
</nav>
<div class="panel-card flush">
@if ($requests->count())
<div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Account</th><th>Request</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($requests as $r)
    <tr>
        <td><b>{{ $r->account->nickname }}</b><small>{{ $r->account->masked() }} &middot; {{ $r->reference }} &middot; {{ $r->created_at->format('M j, g:i a') }}</small></td>
        <td>
            @if ($r->type === 'invite')
                <a class="main" href="{{ route('admin.members.show', $r->requester) }}">{{ $r->requester->name }}</a> invited <a class="main" href="{{ route('admin.members.show', $r->subject) }}">{{ $r->subject->name }}</a>
                <small>ID {{ $r->subject->kyc_status }} &middot; member #{{ $r->subject->member_number }}</small>
            @else
                <a class="main" href="{{ route('admin.members.show', $r->requester) }}">{{ $r->requester->name }}</a> asked to remove <a class="main" href="{{ route('admin.members.show', $r->subject) }}">{{ $r->subject->name }}</a>
            @endif
            @if ($r->message)<small>&ldquo;{{ $r->message }}&rdquo;</small>@endif
        </td>
        <td><span class="chip {{ ['approved' => 'completed', 'done' => 'completed', 'rejected' => 'rejected', 'declined' => 'rejected', 'cancelled' => 'rejected'][$r->status] ?? 'pending' }}">{{ ['invited' => 'Waiting for invitee', 'review' => 'To approve', 'consent' => 'Waiting for holder', 'approved' => 'Approved', 'done' => 'Done', 'rejected' => 'Declined', 'declined' => 'Declined by member', 'cancelled' => 'Cancelled'][$r->status] ?? $r->status }}</span></td>
        <td class="go" style="min-width:16rem">
            @if ($r->type === 'invite' && $r->status === 'review')
                <form method="post" action="{{ route('admin.joint.decide', $r->reference) }}" style="display:inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-s sm" type="submit">Approve</button></form>
                <details class="act" style="margin-top:.5rem"><summary>Decline</summary>
                    <form class="sform" method="post" action="{{ route('admin.joint.decide', $r->reference) }}">@csrf<input type="hidden" name="decision" value="reject">
                        <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                        <label>Message to the members<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                        <button type="submit" class="plain">Decline request</button></form></details>
            @elseif ($r->type === 'removal' && $r->status === 'consent')
                <details class="act"><summary>Remove them anyway</summary>
                    <form class="sform" method="post" action="{{ route('admin.joint.remove', $r->subject) }}">@csrf<input type="hidden" name="account_id" value="{{ $r->account_id }}">
                        <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                        <label>What we found (kept in the audit log)<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                        <button type="submit" class="plain">Remove holder</button></form></details>
            @endif
        </td>
    </tr>
@endforeach
</tbody></table></div>
@else<div class="empty"><b>Nothing here</b>No requests in this list.</div>@endif
</div>
@if ($requests->hasPages())<p class="pager" style="margin-top:1rem">{{ $requests->links() }}</p>@endif
@endsection
