@extends('layouts.portal')
@section('title', $member->name)
@section('content')
<p style="margin:0 0 .5rem"><a class="plink" href="{{ route('admin.members.index') }}">&larr; Members</a></p>
<h1>{{ $member->name }}</h1>
<p class="psub">Member #{{ $member->member_number }} &middot; {{ $member->email }} &middot; {{ $member->phone }} &middot; Joined {{ $member->created_at->format('M j, Y') }}
    @if ($member->profile) &middot; Eligibility: {{ str($member->profile->field_of_membership)->replace('_', ' ') }}@endif</p>

@foreach ($restrictions as $r)
    <div class="notice-bar" style="border-left-color:#b3261e;background:#fdecec;border-color:#e3a1a1">
        <p><strong>{{ str($r->type)->replace('_', ' ')->title() }}</strong> on {{ $r->account ? $r->account->nickname ?? $r->account->masked() : 'the whole member' }}: {{ $r->reason_text }} <span class="fine" style="margin:0">({{ $r->locked_at->format('M j') }})</span></p>
        @can('staff.lockAccounts')
        <form class="sform" method="post" action="{{ route('admin.locks.unlock', $r) }}" style="grid-template-columns:1fr auto;align-items:end;margin-top:.5rem">@csrf
            <label>Note to unlock<input name="note" required></label><button type="submit" class="plain">Unlock</button></form>
        @endcan
    </div>
@endforeach
@if ($errors->any())<div class="flash bad" role="alert">@foreach ($errors->all() as $e){{ $e }} @endforeach</div>@endif

<div class="cols">
@foreach ($accounts as $a)
    <div class="panel-card">
        <span class="tag ok">{{ $a->product->name }}</span> @if ($a->status !== 'active')<span class="tag lock">{{ $a->status }}</span>@endif
        <h3 style="margin-top:.6rem">{{ $a->nickname }} {{ $a->masked() }}</h3>
        <div class="acct" style="border:0;padding:0;box-shadow:none"><span class="bal">{{ $a->balance->format() }}</span></div>
        <p class="fine" style="margin:.3rem 0 0">Available {{ $a->available_balance->format() }}@if ($a->held_balance->cents) &middot; {{ $a->held_balance->format() }} reserved @endif</p>
    </div>
@endforeach
</div>

<div class="cols" style="margin-top:1.25rem">
@can('staff.impersonate', $member)
<div class="panel-card">
    <h3>Log in as member</h3>
    <p class="fine" style="margin-top:0">Opens their online banking for 30 minutes. A reason is required and the member can see it.</p>
    <form class="sform" method="post" action="{{ route('admin.impersonate.start', $member) }}">@csrf
        <label>Reason<select name="reason_code" required>@foreach ($actingReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select>@error('reason_code')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Ticket or reference (optional)<input name="ticket" maxlength="60"></label>
        <label>Note (optional)<input name="reason_text" maxlength="500"></label>
        <button type="submit" class="gold">Log in as member</button>
    </form>
</div>
@endcan

@can('staff.lockAccounts')
<div class="panel-card">
    <h3>Lock</h3>
    <form class="sform" method="post" action="{{ route('admin.locks.store', $member) }}">@csrf
        <label>Apply to<select name="target"><option value="member">The whole member</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <label>Type<select name="type">@foreach (\App\Services\RestrictionService::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($lockReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Details the member can see<textarea name="reason_text" rows="2" required></textarea>@error('reason_text')<span class="err">{{ $message }}</span>@enderror</label>
        <button type="submit" class="red">Apply lock</button>
    </form>
</div>
@endcan

@can('staff.canAdjust')
<div class="panel-card">
    <h3>Adjust balance</h3>
    <form class="sform" method="post" action="{{ route('admin.adjustments.store', $member) }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Account<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <label>Direction<select name="direction"><option value="credit">Credit (add money)</option><option value="debit">Debit (take money)</option></select></label>
        <label>Amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required>@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Description the member sees<input name="description" value="{{ old('description') }}" maxlength="140" required></label>
        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($adjustReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Internal note<textarea name="note" rows="2" required>{{ old('note') }}</textarea></label>
        <button type="submit">Post adjustment</button>
    </form>
    <p class="fine">Amounts of {{ \App\Support\Money::cents(config('bank.review_threshold'))->format() }} or more wait for another staff member. {{ \App\Support\Money::cents(config('bank.dual_approval_threshold'))->format() }} or more need two.</p>
</div>
@endcan
</div>

@if ($waiting->count())
<div class="panel-card" style="margin-top:1.25rem"><h3>Waiting for review</h3><ul class="tl">
@foreach ($waiting as $tx)<li><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}</a> <span class="tag {{ $tx->status->value }}">{{ $tx->status->value }}</span><small>{{ $tx->created_at->format('M j, g:i a') }}</small></li>@endforeach
</ul></div>
@endif

<div class="cols" style="margin-top:1.25rem">
<div class="panel-card"><h3>Recent transactions</h3><ul class="tl">
@forelse ($transactions as $tx)<li><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}</a> <span class="tag {{ $tx->status->value === 'completed' ? 'ok' : $tx->status->value }}">{{ $tx->status->value }}</span><small>{{ $tx->effective_date->format('M j, Y') }} &middot; {{ $tx->member_description ?? $tx->memo }}</small></li>@empty<li>No transactions yet.</li>@endforelse
</ul></div>
<div class="panel-card"><h3>Recent audit entries</h3><ul class="tl">
@forelse ($audit as $a)<li><span class="mono">{{ $a->action }}</span><small>{{ $a->created_at->format('M j, g:i a') }}@if ($a->impersonator_id) &middot; staff acting @endif</small></li>@empty<li>Nothing yet.</li>@endforelse
</ul></div>
<div class="panel-card"><h3>Staff access to this member</h3><ul class="tl">
@forelse ($sessions as $s)<li>{{ $s->admin->name }} <small>{{ $s->started_at->format('M j, g:i a') }} &middot; {{ $s->reason_code }}@if ($s->ended_reason) &middot; {{ $s->ended_reason }}@endif</small></li>@empty<li>None.</li>@endforelse
</ul></div>
</div>
@endsection
