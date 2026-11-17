@extends('layouts.portal')
@section('title', 'Loan '.$loan->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('admin.loans.index', ['tab' => 'loans']) }}">&larr; Loans</a></p>
<h1>{{ $loan->product->name }} {{ $loan->reference }}</h1>
<p class="psub"><a style="color:#fff" href="{{ route('admin.members.show', $loan->user) }}">{{ $loan->user->name }}</a> &middot; {{ $loan->apr() }} APR &middot; {{ $loan->term_months }} months &middot; <span class="tag">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></p>
@endsection
@section('content')
<div class="strip"><div><b>{{ $loan->principalMoney()->format() }}</b><span>Borrowed</span></div><div><b>{{ $loan->balanceMoney()->format() }}</b><span>Balance</span></div><div><b>{{ $loan->autopayAccount ? 'On' : 'Off' }}</b><span>Autopay</span></div></div>
<div class="panel-card flush"><div style="overflow-x:auto"><table class="dtable">
    <thead><tr><th>#</th><th>Due</th><th>Payment</th><th>Principal</th><th>Interest</th><th>Balance after</th><th></th></tr></thead><tbody>
    @foreach ($loan->schedule as $i)
        <tr><td>{{ $i->installment }}</td><td>{{ $i->due_date->format('M j, Y') }}</td><td>{{ $i->payment()->format() }}@if ($i->late_fee)<small>+ {{ \App\Support\Money::cents($i->late_fee)->format() }} late fee</small>@endif</td><td>{{ \App\Support\Money::cents($i->principal)->format() }}</td><td>{{ \App\Support\Money::cents($i->interest)->format() }}</td><td>{{ \App\Support\Money::cents($i->balance_after)->format() }}</td><td>@if ($i->isPaid())<span class="chip completed">Paid</span>@elseif ($i->late_fee)<span class="chip rejected">Past due</span>@endif</td></tr>
    @endforeach
    </tbody></table></div></div>
@endsection
