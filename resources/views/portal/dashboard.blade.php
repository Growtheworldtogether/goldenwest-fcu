@extends('layouts.portal')
@section('title', 'Accounts')
@section('content')
@php($kinds = ['share_savings' => 'Savings', 'share_draft' => 'Checking', 'money_market' => 'Money market'])
<h1>Accounts</h1>
<p class="psub">Welcome back, {{ auth()->user()->first_name ?? auth()->user()->name }}.</p>

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

<div class="mgrid">
<div class="mmain">
    <div class="panel-card flush">
        <div class="grouphead"><h2>Deposit accounts</h2><span>Total balance <b>{{ $total->format() }}</b></span></div>
        <table class="dtable">
            <thead><tr><th>Account</th><th class="num">Available balance</th><th class="num hide-s">Current balance</th><th></th></tr></thead>
            <tbody>
            @foreach ($accounts as $a)
                <tr>
                    <td><a class="main" href="{{ route('accounts.show', $a) }}">{{ $a->nickname }}</a><small>{{ $kinds[$a->product->kind] ?? $a->product->name }} {{ $a->masked() }}</small></td>
                    <td class="num">{{ $a->available_balance->format() }}@if ($a->held_balance->cents > 0)<small>{{ $a->held_balance->format() }} on hold</small>@endif</td>
                    <td class="num hide-s">{{ $a->balance->format() }}</td>
                    <td class="go"><a class="plink" href="{{ route('transfers.create', ['from' => $a->id]) }}">Transfer</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="table-title"><h2>Recent activity</h2><a class="plink" href="{{ route('activity') }}">View all activity</a></div>
    <div class="panel-card flush">
        @if (count($recent))
        <table class="dtable">
            <thead><tr><th>Date</th><th>Description</th><th class="hide-s">Account</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @foreach ($recent as $r)
                <tr>
                    <td style="white-space:nowrap">{{ $r['tx']->effective_date->format('M j') }}</td>
                    <td><a class="main" href="{{ route('transactions.show', $r['tx']->reference) }}">{{ $r['title'] }}</a>@if ($r['tx']->status->value !== 'completed')<small>{{ ['pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'][$r['tx']->status->value] }}</small>@endif</td>
                    <td class="hide-s">{{ $r['account']->nickname }} {{ $r['account']->masked() }}</td>
                    <td class="num {{ $r['cents'] > 0 ? 'in' : '' }}">{{ $r['cents'] > 0 ? '+' : '-' }}@money(abs($r['cents']))</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <div class="empty"><b>No activity yet</b>Deposits and transfers show up here.</div>
        @endif
    </div>
</div>

<aside class="mside">
    <div class="panel-card flush">
        <h2 class="railhead">Quick links</h2>
        <ul class="qlinks">
            <li><a href="{{ route('transfers.create') }}">Transfer money</a></li>
            <li><a href="{{ route('accounts.create') }}">Open an account</a></li>
            <li><a href="{{ route('activity') }}">Search and download activity</a></li>
            <li><a href="{{ route('security.settings') }}">Security center</a></li>
            <li><a href="{{ route('profile') }}">Profile and alerts</a></li>
        </ul>
    </div>
    <div class="panel-card flush">
        <div class="railnote"><b>Customer Care</b>800-555-0147<br>Mon-Fri 7am - 8pm PT<br>Sat 8am - 5pm PT</div>
    </div>
</aside>
</div>
@endsection
