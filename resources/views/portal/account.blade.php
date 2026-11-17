@extends('layouts.portal')
@section('title', $account->nickname)
@section('content')
<p style="margin:0 0 .5rem"><a class="plink" href="{{ route('dashboard') }}">&larr; All accounts</a></p>
<h1>{{ $account->nickname }}</h1>
<p class="psub">{{ $account->product->name }} &middot; Account {{ $account->masked() }}</p>

<div class="two" style="margin-bottom:1.5rem">
    <div class="panel-card" style="margin:0">
        <span class="fine" style="margin:0">Current balance</span>
        <div class="acct" style="border:0;padding:0"><span class="bal">{{ $account->balance->format() }}</span></div>
        <p class="avail" style="margin:.4rem 0 0">Available {{ $account->available_balance->format() }}@if ($account->held_balance->cents > 0) &middot; {{ $account->held_balance->format() }} on hold @endif</p>
        <p style="margin:1rem 0 0"><a class="btn" href="{{ route('transfers.create', ['from' => $account->id]) }}">Transfer money</a></p>
    </div>
    <div class="panel-card" style="margin:0">
        <h2>Account details</h2>
        <dl class="kv">
            <dt>Type</dt><dd>{{ $account->product->name }}</dd>
            <dt>Earns</dt><dd>{{ number_format($account->product->dividend_rate_bps / 100, 2) }}% APY</dd>
            <dt>Opened</dt><dd>{{ $account->created_at->format('M j, Y') }}</dd>
            @if ($account->overdraftAccount)<dt>Overdraft protection</dt><dd>{{ $account->overdraftAccount->nickname }} {{ $account->overdraftAccount->masked() }}</dd>@endif
        </dl>
        <details style="margin-top:1rem"><summary class="plink" style="cursor:pointer">Show account and routing numbers</summary>
            <dl class="kv" style="margin-top:.6rem"><dt>Routing</dt><dd>121478906</dd><dt>Account</dt><dd>{{ $account->account_number }}</dd></dl>
        </details>
    </div>
</div>

<h2>Activity</h2>
@include('portal._history', ['exportUrl' => route('accounts.export', [$account] + request()->query())])
@endsection
