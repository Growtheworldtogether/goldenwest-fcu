@extends('layouts.portal')
@section('title', 'Loans')
@section('band')
<div class="pband-row">
    <div><h1>Loans</h1><p class="psub">Your loans, payments and applications.</p></div>
    <div class="macts"><a class="btn-s gold" href="{{ route('borrow.create') }}">Apply for a loan</a></div>
</div>
@endsection
@section('content')
@foreach ($applications->where('status', '!=', 'rejected') as $a)
    <div class="notice-bar" role="status"><p><strong>{{ $a->product->name }} application {{ $a->reference }}:</strong> {{ $a->statusLabel() }}. <a class="plink" href="{{ route('borrow.application', $a->reference) }}">View</a></p></div>
@endforeach

<div class="panel-card flush">
    <div class="grouphead"><h2>Your loans</h2></div>
    @if ($loans->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Loan</th><th>Balance</th><th>Rate</th><th>Next payment</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($loans as $l)
            <tr>
                <td><a class="main" href="{{ route('borrow.show', $l->reference) }}">{{ $l->product->name }}</a><small>{{ $l->reference }}</small></td>
                <td>{{ $l->balanceMoney()->format() }}<small>of {{ $l->principalMoney()->format() }}</small></td>
                <td>{{ $l->apr() }}</td>
                <td>{{ $l->next_due_date?->format('M j, Y') ?? 'None' }}</td>
                <td><span class="chip {{ ['active' => 'completed', 'paid_off' => 'completed', 'delinquent' => 'rejected'][$l->status] }}">{{ ['active' => 'Current', 'paid_off' => 'Paid off', 'delinquent' => 'Past due'][$l->status] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>No loans yet</b>Apply for a personal or auto loan and decisions show up here.</div>
    @endif
</div>

<div class="table-title"><h2>Loans we offer</h2></div>
<div class="cols" style="grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))">
@foreach ($products as $p)
    <div class="panel-card"><h3>{{ $p->name }}</h3><p style="margin:0 0 .5rem">{{ $p->aprRange() }}</p><p class="fine" style="margin:0 0 .8rem">{{ $p->minAmount()->format() }} to {{ $p->maxAmount()->format() }}, {{ min($p->terms) }} to {{ max($p->terms) }} months</p><a class="btn-s plain sm" href="{{ route('borrow.create', ['type' => $p->code]) }}">Apply</a></div>
@endforeach
</div>

@if ($applications->where('status', 'rejected')->count())
<div class="table-title"><h2>Past applications</h2></div>
<div class="panel-card flush"><table class="dtable"><tbody>
@foreach ($applications->where('status', 'rejected') as $a)
    <tr><td>{{ $a->product->name }}<small>{{ $a->reference }} &middot; {{ $a->created_at->format('M j, Y') }}</small></td><td>{{ $a->money()->format() }}</td><td><span class="chip rejected">Not approved</span> <a class="plink" href="{{ route('borrow.application', $a->reference) }}">Details</a></td></tr>
@endforeach
</tbody></table></div>
@endif
@endsection
