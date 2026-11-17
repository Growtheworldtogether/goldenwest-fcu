@extends('layouts.portal')
@section('title', 'Wire transfers')
@section('band')
<div class="pband-row">
    <div><h1>Wire transfers</h1><p class="psub">Send money to an account at another bank, usually the same business day.</p></div>
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
                <td>{{ $w->payee->name }}<small>{{ $w->payee->label() }}</small></td>
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
<p class="fine">Every wire is reviewed by a person before it is sent. Wires cannot be taken back once they are sent, so check the details carefully. To send to a new account, add it under <a class="plink" href="{{ route('payees.index') }}">External accounts</a> first.</p>
@endsection
