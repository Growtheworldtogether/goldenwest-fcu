@extends('layouts.portal')
@section('title', 'Deposit')
@section('band')
<h1>Deposit money</h1>
<p class="psub">Add a check from your phone or bring money in from an account at another bank. Cash can be deposited at any {{ brand('short_name') }} branch.</p>
@endsection
@section('content')
@unless ($verified)
    <div class="notice-bar" role="status"><p><strong>Verify your identity first.</strong> We ask every member to confirm who they are once before they deposit checks or move money from another bank.</p><p><a class="btn" href="{{ route('kyc.show') }}">Verify my identity</a></p></div>
@endunless

<div class="choice">
    <a class="panel-card choicecard" href="{{ route('deposits.check') }}"><h3>Deposit a check</h3><p>Take a photo of the front and back. A person reviews it, usually the same business day.</p></a>
    <a class="panel-card choicecard" href="{{ route('deposits.ach') }}"><h3>From another bank</h3><p>Pull money from your account at another bank. It arrives the next business day after review.</p></a>
    <a class="panel-card choicecard" href="{{ route('payees.index') }}"><h3>External accounts</h3><p>Add and manage the accounts at other banks you send money to or pull money from.</p></a>
</div>

<div class="table-title"><h2>Recent deposits</h2></div>
<div class="panel-card flush">
    @if ($deposits->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Date</th><th>How</th><th>Into</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($deposits as $d)
            @php($tx = $d->transaction)
            @php($label = ['pending' => 'In review', 'held' => 'On hold', 'rejected' => 'Declined', 'completed' => 'Posted', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'][$tx->status->value] ?? $tx->status->value)
            <tr>
                <td>{{ $d->created_at->format('M j, Y') }}</td>
                <td>{{ $d->methodLabel() }}@if ($d->method === 'ach' && $d->payee)<small>{{ $d->payee->label() }}</small>@elseif ($d->check_number)<small>Check #{{ $d->check_number }}</small>@endif</td>
                <td>{{ $d->account->nickname }} {{ $d->account->masked() }}</td>
                <td class="num">{{ $tx->amount->format() }}</td>
                <td><span class="chip {{ $tx->status->value }}">{{ $label }}</span></td>
                <td class="go"><a class="plink" href="{{ route('transactions.show', $tx->reference) }}">Details</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>No deposits yet</b>When you deposit a check or pull money from another bank it shows here.</div>
    @endif
</div>
@endsection
