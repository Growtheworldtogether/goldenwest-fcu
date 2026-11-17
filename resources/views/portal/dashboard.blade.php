@extends('layouts.portal')
@section('title', 'Accounts')
@section('content')
@php
    $avail = \App\Support\Money::cents($accounts->sum(fn ($a) => $a->available_balance->cents));
    $held = \App\Support\Money::cents($accounts->sum(fn ($a) => $a->held_balance->cents));
@endphp
<p class="hello">Welcome back, <strong>{{ auth()->user()->first_name ?? auth()->user()->name }}</strong></p>

<section class="summary" aria-label="Total balance">
    <div class="lab">Total balance</div>
    <div class="big">{{ $total->format() }}</div>
    <div class="row">
        <div><span class="lab">Available</span><b>{{ $avail->format() }}</b></div>
        @if ($held->cents > 0)<div><span class="lab">On hold</span><b>{{ $held->format() }}</b></div>@endif
        <div><span class="lab">Accounts</span><b>{{ $accounts->count() }}</b></div>
    </div>
</section>

<nav class="quick" aria-label="Quick actions">
    <a href="{{ route('transfers.create') }}"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h13l-3.500-3.500M18 16H5l3.500 3.500"/></svg></span><span>Transfer money<small>Between your accounts</small></span></a>
    <a href="{{ route('accounts.create') }}"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span><span>Open an account<small>Free, no deposit</small></span></a>
    <a href="{{ route('activity') }}"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg></span><span>View activity<small>Search and download</small></span></a>
</nav>

@foreach ($locks as $lock)
    <div class="notice-bar" role="status">
        <p><strong>{{ $lock->account ? $lock->account->nickname.' '.$lock->account->masked() : 'Your membership' }} has a restriction:</strong> {{ ['full_lock' => 'No money can move in or out', 'debit_block' => 'Money cannot be sent out', 'credit_block' => 'Money cannot be received'][$lock->type] ?? '' }}.</p>
        <p>{{ $reasons[$lock->reason_code] ?? '' }}. You can still sign in and see your balances. <a class="plink" href="{{ url('/help#contest') }}">Contact us about this</a>.</p>
    </div>
@endforeach

@foreach ($waiting as $tx)
    @php($d = $tx->decisions->sortByDesc('id')->first())
    <div class="notice-bar" role="status">
        <p><strong>{{ $tx->status->value === 'held' ? 'On hold' : 'Under review' }}:</strong> {{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}@if ($tx->member_description), {{ $tx->member_description }}@endif</p>
        @if ($d && $d->decision === 'held')
            <p>{{ $reasons[$d->reason_code] ?? 'Under review' }}. {{ $d->reason_text }} @if ($d->hold_until) We expect to finish by {{ $d->hold_until->format('M j') }}.@endif</p>
        @else
            <p>We are reviewing this payment. The money is set aside and you will see an update here.</p>
        @endif
        <p><a class="plink" href="{{ route('transactions.show', $tx->reference) }}">View details</a></p>
    </div>
@endforeach

<div class="sechead"><h2>Your accounts</h2></div>
<div class="pcards">
    @foreach ($accounts as $a)
        <article class="acct">
            <span class="kind">{{ ['share_savings' => 'Savings', 'share_draft' => 'Checking', 'money_market' => 'Money market'][$a->product->kind] ?? str($a->product->kind)->replace('_', ' ')->title() }}</span>
            <h3>{{ $a->nickname }}</h3>
            <span class="num">Account {{ $a->masked() }}</span>
            <span class="bal">{{ $a->balance->format() }}</span>
            <span class="avail">Available {{ $a->available_balance->format() }}@if ($a->held_balance->cents > 0) &middot; {{ $a->held_balance->format() }} on hold @endif</span>
            <div class="actions"><a href="{{ route('accounts.show', $a) }}">Activity</a><a href="{{ route('transfers.create', ['from' => $a->id]) }}">Transfer</a></div>
        </article>
    @endforeach
</div>

<div class="sechead"><h2>Recent activity</h2><a class="plink" href="{{ route('activity') }}">See all</a></div>
<div class="panel-card" style="padding:0;overflow:hidden">
    @if (count($recent))
        <ul class="feed">
            @foreach ($recent as $r)
                @php($ini = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $r['title']) ?: 'T', 0, 2)))
                <li>
                    <span class="dot c{{ crc32($r['title']) % 5 }}" aria-hidden="true">{{ $ini }}</span>
                    <span class="what"><a href="{{ route('transactions.show', $r['tx']->reference) }}">{{ $r['title'] }}</a><small>{{ $r['tx']->effective_date->format('M j') }} &middot; {{ $r['account']->nickname }} {{ $r['account']->masked() }}@if ($r['tx']->status->value !== 'completed') &middot; {{ ['pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'][$r['tx']->status->value] }}@endif</small></span>
                    <span class="amt {{ $r['cents'] > 0 ? 'in' : '' }}">{{ $r['cents'] > 0 ? '+' : '-' }}@money(abs($r['cents']))</span>
                </li>
            @endforeach
        </ul>
    @else
        <p style="padding:1.5rem;margin:0">Nothing yet. Make a deposit or transfer to get started.</p>
    @endif
</div>
@endsection
