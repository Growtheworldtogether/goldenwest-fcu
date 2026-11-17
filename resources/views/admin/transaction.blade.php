@extends('layouts.portal')
@section('title', 'Transaction')
@section('content')
@php($open = in_array($tx->status->value, ['pending', 'held']))
@php($owner = $owners[$tx->from_account_id] ?? $owners[$tx->to_account_id] ?? null)
<p style="margin:0 0 .5rem"><a class="plink" href="{{ route('admin.queue.index') }}">&larr; Review queue</a></p>
<h1>{{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}</h1>
<p class="psub"><span class="tag {{ $tx->status->value === 'completed' ? 'ok' : $tx->status->value }}">{{ $tx->status->value }}</span> &middot; {{ $tx->created_at->format('M j, Y g:i a') }} &middot; Ref {{ $tx->reference }}</p>
@if ($errors->any())<div class="flash bad" role="alert">@foreach ($errors->all() as $e){{ $e }} @endforeach</div>@endif

<div class="cols">
<div class="panel-card">
    <h3>Details</h3>
    <dl class="kv">
        <dt>Member</dt><dd>@if ($owner)<a class="plink" href="{{ route('admin.members.show', $owner) }}">{{ $owner->name }}</a>@else Credit union @endif</dd>
        @if ($tx->fromAccount)<dt>From</dt><dd>{{ $tx->fromAccount->nickname }} {{ $tx->fromAccount->masked() }}</dd>@endif
        @if ($tx->toAccount)<dt>To</dt><dd>{{ $tx->toAccount->nickname }} {{ $tx->toAccount->masked() }}</dd>@endif
        <dt>Fee</dt><dd>{{ $tx->fee->format() }}</dd>
        <dt>Description</dt><dd>{{ $tx->member_description ?? 'None' }}</dd>
        <dt>Internal memo</dt><dd>{{ $tx->memo ?? 'None' }}</dd>
        <dt>Started by</dt><dd>{{ $initiator?->name ?? 'System' }}@if ($impersonator) <span class="tag lock">via {{ $impersonator->name }}</span>@endif</dd>
        @if ($open)<dt>Approvals</dt><dd>{{ $approvers }} of {{ $needed }}@if ($needed > 1) (two different approvers needed)@endif</dd>@endif
    </dl>
</div>
<div class="panel-card">
    <h3>Decisions</h3>
    <ul class="tl">
    @forelse ($tx->decisions as $d)
        <li><strong>{{ ucfirst($d->decision) }}</strong> by {{ $d->decider->name }}<small>{{ $d->created_at->format('M j, g:i a') }}@if ($d->reason_code) &middot; {{ $allReasons[$d->reason_code] ?? $d->reason_code }}@endif @if ($d->hold_until) &middot; until {{ $d->hold_until->format('M j') }}@endif</small>@if ($d->reason_text)<small style="color:var(--ink)">{{ $d->reason_text }}</small>@endif</li>
    @empty
        <li>No decisions yet.</li>
    @endforelse
    </ul>
</div>
</div>

@if ($open && $canDecide)
<div class="panel-card decide" style="margin-top:1.25rem">
    <h3>Decide</h3>
    <details class="act" open><summary>Approve</summary>
        <form class="sform" method="post" action="{{ route('admin.queue.act', [$tx->reference, 'approve']) }}">@csrf
            <label>Note (optional)<input name="note" maxlength="500"></label><button type="submit" class="gold">Approve</button></form></details>
    <details class="act"><summary>Place on hold</summary>
        <form class="sform" method="post" action="{{ route('admin.queue.act', [$tx->reference, 'hold']) }}">@csrf
            <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($reasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
            <label>What the member sees<textarea name="reason_text" rows="2" required></textarea></label>
            <label>Expected by (optional)<input type="date" name="until"></label><button type="submit" class="plain">Place on hold</button></form></details>
    <details class="act"><summary>Reject</summary>
        <form class="sform" method="post" action="{{ route('admin.queue.act', [$tx->reference, 'reject']) }}">@csrf
            <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($reasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
            <label>What the member sees<textarea name="reason_text" rows="2" required></textarea></label><button type="submit" class="red">Reject</button></form></details>
    @if ($tx->status->value === 'held')
    <details class="act"><summary>Release hold</summary>
        <form class="sform" method="post" action="{{ route('admin.queue.act', [$tx->reference, 'release']) }}">@csrf
            <label>Note (optional)<input name="note" maxlength="500"></label><button type="submit" class="plain">Release</button></form></details>
    @endif
</div>
@elseif ($open)
<div class="notice-bar" style="margin-top:1.25rem"><p>Your title can view this transaction but not decide it.</p></div>
@endif

@if ($tx->status->value === 'completed' && $canReverse && $canDecide)
<div class="panel-card" style="margin-top:1.25rem;max-width:36rem"><h3>Reverse</h3>
    <form class="sform" method="post" action="{{ route('admin.queue.act', [$tx->reference, 'reverse']) }}">@csrf
        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($reasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Explanation<textarea name="reason_text" rows="2" required></textarea></label><button type="submit" class="red">Reverse this transaction</button></form>
</div>
@endif
@endsection
