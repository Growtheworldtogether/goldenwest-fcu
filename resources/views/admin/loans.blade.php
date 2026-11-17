@extends('layouts.portal')
@section('title', 'Loans')
@section('band')
<h1>Loans</h1>
<p class="psub">Review applications and watch active loans.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Filter">@foreach ($tabs as $k => $l)<a href="{{ route('admin.loans.index', ['tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
<div class="panel-card flush">
@if ($tab === 'loans')
    @if ($loans->count())
    <div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Loan</th><th>Member</th><th>Balance</th><th>Next due</th><th>Status</th></tr></thead><tbody>
    @foreach ($loans as $l)
        <tr><td><a class="main" href="{{ route('admin.loans.loan', $l->reference) }}">{{ $l->product->name }}</a><small>{{ $l->reference }} &middot; {{ $l->apr() }}</small></td>
        <td>{{ $l->user->name }}<small>#{{ $l->user->member_number }}</small></td><td>{{ $l->balanceMoney()->format() }}</td><td>{{ $l->next_due_date?->format('M j, Y') }}</td>
        <td><span class="chip {{ $l->status === 'delinquent' ? 'rejected' : 'completed' }}">{{ $l->status === 'delinquent' ? 'Delinquent' : 'Current' }}</span></td></tr>
    @endforeach
    </tbody></table></div>
    {{ $loans->links() }}
    @else<div class="empty"><b>No active loans</b></div>@endif
@else
    @if ($apps->count())
    <div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Application</th><th>Member</th><th>Amount</th><th>Sent</th><th>Status</th></tr></thead><tbody>
    @foreach ($apps as $a)
        <tr><td><a class="main" href="{{ route('admin.loans.show', $a->reference) }}">{{ $a->product->name }}</a><small>{{ $a->reference }}</small></td>
        <td>{{ $a->user->name }}<small>#{{ $a->user->member_number }}</small></td><td>{{ $a->money()->format() }}<small>{{ $a->term_months }} months</small></td><td>{{ $a->created_at->format('M j, g:i a') }}</td>
        <td><span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected', 'needs_info' => 'held'][$a->status] ?? 'pending' }}">{{ $a->statusLabel() }}</span></td></tr>
    @endforeach
    </tbody></table></div>
    {{ $apps->links() }}
    @else<div class="empty"><b>Nothing here</b>No applications match.</div>@endif
@endif
</div>
@endsection
