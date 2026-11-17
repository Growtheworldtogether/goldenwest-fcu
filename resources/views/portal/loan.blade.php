@extends('layouts.portal')
@section('title', $loan->product->name)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('borrow.index') }}">&larr; Loans</a></p>
<h1>{{ $loan->product->name }}</h1>
<p class="psub">{{ $loan->reference }} &middot; {{ $loan->apr() }} APR &middot; {{ $loan->term_months }} months &middot; <span class="chip {{ ['active' => 'completed', 'paid_off' => 'completed', 'delinquent' => 'rejected'][$loan->status] }}">{{ ['active' => 'Current', 'paid_off' => 'Paid off', 'delinquent' => 'Past due'][$loan->status] }}</span></p>
@endsection
@section('content')
@php($next = $loan->schedule->firstWhere('status', 'due'))
<div class="strip">
    <div><b>{{ $loan->balanceMoney()->format() }}</b><span>Balance</span></div>
    <div><b>{{ $next ? $next->amountDue()->format() : '$0.00' }}</b><span>Next payment{{ $next ? ', due '.$next->due_date->format('M j') : '' }}</span></div>
    <div><b>{{ $payoff?->format() ?? '$0.00' }}</b><span>Payoff today</span></div>
</div>
@if ($next && $next->late_fee > 0)<div class="notice-bar" role="status"><p><strong>This payment is past due.</strong> A late fee of {{ \App\Support\Money::cents($next->late_fee)->format() }} is included.</p></div>@endif

@if ($loan->isOpen())
<div class="cols tcols">
<div class="panel-card">
    <h3>Make a payment</h3>
    <form class="sform" method="post" action="{{ route('borrow.pay', $loan->reference) }}">@csrf
        <label>Pay from<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }} &middot; {{ $a->available_balance->format() }}</option>@endforeach</select></label>
        <label>What to pay<select name="kind" id="kind"><option value="next">Next payment ({{ $next?->amountDue()->format() }})</option><option value="extra">An extra amount toward principal</option><option value="payoff">Pay off the loan ({{ $payoff->format() }})</option></select></label>
        <label id="amtrow" style="display:none">Extra amount ($)<input name="amount" inputmode="decimal"></label>
        <button type="submit" class="gold">Pay</button>
    </form>
    <p class="fine" style="margin-bottom:0">Extra payments lower your balance and your remaining payments. The payoff amount includes interest up to today.</p>
</div>
<div class="panel-card">
    <h3>Autopay</h3>
    @if ($loan->autopayAccount)
        <p style="margin-top:0">On, from {{ $loan->autopayAccount->nickname ?? $loan->autopayAccount->product->name }} {{ $loan->autopayAccount->masked() }}. We take each payment on its due date.</p>
        <form method="post" action="{{ route('borrow.autopay', $loan->reference) }}">@csrf<button class="btn-s plain sm" type="submit">Turn off autopay</button></form>
    @else
        <p class="fine" style="margin-top:0">Never miss a due date. We take each payment from the account you pick.</p>
        <form class="sform" method="post" action="{{ route('borrow.autopay', $loan->reference) }}">@csrf<input type="hidden" name="on" value="1">
            <label>Account<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }}</option>@endforeach</select></label>
            <button type="submit" class="plain">Turn on autopay</button></form>
    @endif
</div>
</div>
@endif

<div class="table-title"><h2>Payment schedule</h2></div>
<div class="panel-card flush"><div style="overflow-x:auto"><table class="dtable">
    <thead><tr><th>#</th><th>Due</th><th>Payment</th><th>Principal</th><th>Interest</th><th>Balance after</th><th></th></tr></thead>
    <tbody>
    @foreach ($loan->schedule as $i)
        <tr><td>{{ $i->installment }}</td><td>{{ $i->due_date->format('M j, Y') }}</td><td>{{ $i->payment()->format() }}@if ($i->late_fee)<small>+ {{ \App\Support\Money::cents($i->late_fee)->format() }} late fee</small>@endif</td>
        <td>{{ \App\Support\Money::cents($i->principal)->format() }}</td><td>{{ \App\Support\Money::cents($i->interest)->format() }}</td><td>{{ \App\Support\Money::cents($i->balance_after)->format() }}</td>
        <td>@if ($i->isPaid())<span class="chip completed">Paid</span>@elseif ($i->late_fee)<span class="chip rejected">Past due</span>@endif</td></tr>
    @endforeach
    </tbody>
</table></div></div>
@push('scripts')<script>var k=document.getElementById('kind');if(k){k.addEventListener('change',function(){document.getElementById('amtrow').style.display=k.value==='extra'?'block':'none';});}</script>@endpush
@endsection
