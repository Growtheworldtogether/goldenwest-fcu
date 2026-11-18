@extends('layouts.portal')
@section('title', $account->nickname)
@php($kinds = ['share_savings' => 'Savings', 'share_draft' => 'Checking', 'money_market' => 'Money market'])
@section('band')
<p class="crumbs2"><a href="{{ route('dashboard') }}">Accounts</a> &rsaquo; {{ $account->nickname }}</p>
<div class="pband-row">
    <div><h1>{{ $account->nickname }}</h1><p class="psub">{{ $account->kindLabel() }} {{ $account->masked() }}</p></div>
    <a class="btn-s gold" href="{{ route('transfers.create', ['from' => $account->id]) }}">Transfer money</a>
</div>
<div class="figs">
    <div><b>{{ $account->available_balance->format() }}</b><span>Available balance</span></div>
    <div><b>{{ $account->balance->format() }}</b><span>Current balance</span></div>
    @if ($account->held_balance->cents > 0)<div><b>{{ $account->held_balance->format() }}</b><span>On hold</span></div>@endif
</div>
@endsection
@section('content')
<div class="mgrid">
    <div class="mmain">
        <div class="table-title" style="margin-top:0"><h2>Transactions</h2></div>
        @include('portal._history', ['exportUrl' => route('accounts.export', [$account] + request()->query())])
    </div>
    <aside class="mside">
        <div class="panel-card flush">
            <h2 class="railhead">Account details</h2>
            <dl class="kvlist">
                <dt>Type</dt><dd>{{ $account->product->name }}</dd>
                <dt>Rate</dt><dd>{{ number_format($account->product->dividend_rate_bps / 100, 2) }}% APY</dd>
                <dt>Opened</dt><dd>{{ $account->created_at->format('M j, Y') }}</dd>
                @if ($account->overdraftAccount)<dt>Overdraft from</dt><dd>{{ $account->overdraftAccount->nickname }} {{ $account->overdraftAccount->masked() }}</dd>@endif
            </dl>
            <details style="padding:0 1.25rem 1.1rem"><summary class="plink" style="cursor:pointer">Show account and routing numbers</summary>
                <dl class="kvlist" style="padding:.8rem 0 0"><dt>Routing</dt><dd>121478906</dd><dt>Account</dt><dd>{{ $account->account_number }}</dd></dl>
            </details>
        </div>
        @php($holders = app(\App\Services\JointService::class)->activeHolders($account))
        @php($benCount = app(\App\Services\BeneficiaryService::class)->forAccount($account)->count())
        <div class="panel-card flush">
            <h2 class="railhead">Holders and beneficiaries</h2>
            <dl class="kvlist">
                @foreach ($holders as $h)<dt>{{ $h->role === 'primary' ? 'Primary holder' : 'Joint holder' }}</dt><dd>{{ $h->user->name }}</dd>@endforeach
                <dt>Beneficiaries</dt><dd>{{ $benCount ? $benCount.' named' : 'None named' }}</dd>
            </dl>
            <p style="padding:0 1.25rem 1.1rem;margin:0"><a class="plink" href="{{ route('beneficiaries.edit', $account) }}">Manage beneficiaries</a> &middot; <a class="plink" href="{{ route('joint.index', ['account' => $account->id]) }}">{{ $holders->count() > 1 ? 'Joint holders' : 'Add a joint holder' }}</a></p>
        </div>
    </aside>
</div>
@endsection
