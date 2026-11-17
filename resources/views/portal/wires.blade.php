@extends('layouts.portal')
@section('title', 'Wire transfers')
@section('band')
<div class="pband-row">
    <div><h1>Wire transfers</h1><p class="psub">Send money to a bank account in the United States, usually the same business day@if ($international), or abroad in one to three business days@endif.</p></div>
    <div class="macts"><a class="btn-s gold" href="{{ route('wires.create') }}">Send a wire</a></div>
</div>
@endsection
@section('content')
<div class="panel-card flush">
    @if ($wires->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Requested</th><th>To</th><th>Sent on</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($wires as $w)
            @php($tx = $w->transaction)
            @php($label = ['pending' => 'In review', 'held' => 'On hold', 'rejected' => 'Declined', 'completed' => 'Sent', 'failed' => 'Failed', 'reversed' => 'Returned'][$tx->status->value] ?? $tx->status->value)
            <tr>
                <td>{{ $w->created_at->format('M j, Y') }}</td>
                <td>{{ $w->recipient_name }}<small>{{ $w->bank_name }}@if ($w->isInternational()), {{ $w->countryName() }}@endif ending {{ $w->last4 }}</small></td>
                <td>{{ $w->process_on->format('M j, Y') }}</td>
                <td class="num">{{ $tx->amount->format() }}<small>+ {{ $tx->fee->format() }} fee</small></td>
                <td><span class="chip {{ $tx->status->value }}">{{ $label }}</span></td>
                <td class="go"><a class="plink" href="{{ route('transactions.show', $tx->reference) }}">Details</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>No wires yet</b>When you send a wire it shows here with its status.</div>
    @endif
</div>

@if ($recipients->count())
<div class="table-title"><h2>Saved recipients</h2></div>
<div class="panel-card flush">
    <table class="dtable">
        <thead><tr><th>Recipient</th><th>Bank</th><th></th></tr></thead>
        <tbody>
        @foreach ($recipients as $r)
            <tr><td>{{ $r->name }}<small>{{ $r->address }}</small></td><td>{{ $r->bank_name }}<small>@if ($r->isInternational()){{ config('wires.countries')[$r->country] ?? $r->country }}, @endif Account ending {{ $r->last4 }}</small></td>
                <td class="go"><form method="post" action="{{ route('wires.recipients.destroy', $r) }}" onsubmit="return confirm('Remove this recipient?')">@csrf @method('DELETE')<button type="submit" class="linkbtn">Remove</button></form></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<p class="fine">Every wire is reviewed by a person before it is sent, and a wire cannot be taken back once it is sent. Wires are separate from transfers to your own accounts at other banks, which you manage under <a class="plink" href="{{ route('payees.index') }}">External accounts</a>.</p>
@endsection
