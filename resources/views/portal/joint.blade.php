@extends('layouts.portal')
@section('title', 'Joint accounts')
@section('band')
<h1>Joint accounts</h1>
<p class="psub">Share an account with someone you trust. Both holders can see it and use it.</p>
@endsection
@section('content')
@include('portal._people-tabs')

@foreach ($incomingInvites as $r)
<div class="notice-bar" role="status">
    <p><strong>{{ $r->requester->first_name ?? $r->requester->name }} invited you to share {{ $r->account->nickname }}.</strong> @if ($r->message)&ldquo;{{ $r->message }}&rdquo;@endif</p>
    <p>If you accept, a team member reviews it before you get access.</p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <form method="post" action="{{ route('joint.respond', $r->reference) }}">@csrf<input type="hidden" name="action" value="accept"><button class="btn-s gold" type="submit">Accept</button></form>
        <form method="post" action="{{ route('joint.respond', $r->reference) }}">@csrf<input type="hidden" name="action" value="decline"><button class="btn-s plain" type="submit">Decline</button></form>
    </div>
</div>
@endforeach
@foreach ($consents as $r)
<div class="notice-bar" role="status">
    <p><strong>{{ $r->requester->first_name ?? $r->requester->name }} asked to take you off {{ $r->account->nickname }}.</strong> We only do this if you agree, or if Customer Care reviews it.</p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <form method="post" action="{{ route('joint.consent', $r->reference) }}">@csrf<input type="hidden" name="action" value="agree"><button class="btn-s" type="submit">I agree</button></form>
        <form method="post" action="{{ route('joint.consent', $r->reference) }}">@csrf<input type="hidden" name="action" value="decline"><button class="btn-s plain" type="submit">Keep me on the account</button></form>
    </div>
</div>
@endforeach

@foreach ($accounts as $a)
    @php($hs = $holders[$a->id])
    <div class="panel-card">
        <h3 style="margin:0 0 .6rem">{{ $a->nickname }} <small style="font-weight:400;color:var(--muted)">{{ $a->masked() }}</small></h3>
        <table class="dtable"><thead><tr><th>Holder</th><th>Role</th><th></th></tr></thead><tbody>
        @foreach ($hs as $h)
            <tr>
                <td>{{ $h->user->name }}@if ($h->user_id === auth()->id()) <small>(you)</small>@endif</td>
                <td>{{ $h->role === 'primary' ? 'Primary' : 'Joint' }}</td>
                <td class="go">
                    @if ($hs->count() > 1)
                        @if ($h->user_id === auth()->id())
                            <form method="post" action="{{ route('joint.leave') }}" onsubmit="return confirm('Leave this account? You will lose access to it.')">@csrf<input type="hidden" name="account_id" value="{{ $a->id }}"><button class="linkbtn" type="submit">Leave this account</button></form>
                        @else
                            <form method="post" action="{{ route('joint.removal') }}" onsubmit="return confirm('Ask {{ $h->user->first_name ?? 'them' }} to come off this account?')">@csrf<input type="hidden" name="account_id" value="{{ $a->id }}"><input type="hidden" name="user_id" value="{{ $h->user_id }}"><button class="linkbtn" type="submit">Ask to remove</button></form>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody></table>
        @if ($hs->count() < \App\Services\JointService::MAX_HOLDERS)
            @if ($verified)
            <details class="act" @if ($pick === $a->id) open @endif style="margin-top:.8rem"><summary>Add a joint holder</summary>
                <form class="sform" method="post" action="{{ route('joint.invite') }}">@csrf<input type="hidden" name="account_id" value="{{ $a->id }}">
                    <label>Their email address<input type="email" name="email" value="{{ old('email') }}" required maxlength="190"></label>
                    <label>A short note (optional)<input name="message" value="{{ old('message') }}" maxlength="200"></label>
                    <p class="fine" style="margin:0">They need a {{ brand('short_name') }} membership with a verified identity. They accept first, then a team member reviews the request.</p>
                    <button type="submit" class="plain">Send invitation</button></form>
            </details>
            @else
                <p class="fine" style="margin:.8rem 0 0">Verify your identity to add a joint holder. <a class="plink" href="{{ route('kyc.show') }}">Verify now</a></p>
            @endif
        @endif
    </div>
@endforeach

@if ($open->count() || $recent->count())
<div class="table-title"><h2>Requests</h2></div>
<div class="panel-card flush"><table class="dtable"><thead><tr><th>Account</th><th>Request</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach ($open->concat($recent) as $r)
        <tr>
            <td>{{ $r->account->nickname }}<small>{{ $r->created_at->format('M j, Y') }}</small></td>
            <td>{{ $r->type === 'invite' ? 'Invite '.($r->subject?->name ?? $r->invitee_email) : 'Remove '.($r->subject?->name ?? 'a holder') }}</td>
            <td><span class="chip {{ ['rejected' => 'rejected', 'declined' => 'rejected'][$r->status] ?? 'pending' }}">{{ ['invited' => 'Waiting for them', 'review' => 'In review', 'consent' => 'Waiting for their answer', 'rejected' => 'Declined by us', 'declined' => 'Declined'][$r->status] ?? $r->status }}</span>
                @if ($r->status === 'rejected' && ($d = $r->lastDecision()))<small>{{ $d->reason_text }}</small>@endif</td>
            <td class="go">@if ($r->isOpen() && $r->requested_by === auth()->id())<form method="post" action="{{ route('joint.cancel', $r->reference) }}">@csrf<button class="linkbtn" type="submit">Cancel</button></form>@endif</td>
        </tr>
    @endforeach
</tbody></table></div>
@endif
<p class="fine">Both holders have full access. A lock on the account or on either holder applies to everyone on it. Taking someone off needs their agreement, or a Customer Care review.</p>
@endsection
