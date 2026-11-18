@extends('layouts.portal')
@section('title', 'Application '.$app->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('borrow.index') }}">&larr; Loans</a></p>
<h1>{{ $app->product->name }} application</h1>
<p class="psub">{{ $app->reference }} &middot; sent {{ $app->created_at->format('F j, Y') }} &middot; <span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected', 'needs_info' => 'held'][$app->status] ?? 'pending' }}">{{ $app->statusLabel() }}</span></p>
@endsection
@section('content')
@php($d = $app->decisions->sortByDesc('id')->first())
@if ($app->status === 'needs_info')
<div class="panel-card" style="margin-bottom:1.25rem">
    <h3>We need a little more from you</h3>
    <p style="white-space:pre-line">{{ $app->info_request }}</p>
    <form class="form" method="post" action="{{ route('borrow.reply', $app->reference) }}">@csrf
        <label>Your answer<textarea name="reply" rows="4" maxlength="2000" required></textarea>@error('reply')<span class="err">{{ $message }}</span>@enderror</label>
        <button class="btn" type="submit">Send answer</button>
    </form>
</div>
@endif
@if ($app->status === 'rejected')
    <div class="notice-bar" role="status"><p><strong>We could not approve this application.</strong> {{ $reasons[$d?->reason_code] ?? '' }}. {{ $d?->reason_text }}</p><p>You are welcome to apply again, or call Customer Care at {{ brand('support_phone') }} to talk it through.</p></div>
@endif
@if ($app->status === 'approved' && $app->loan)
    <div class="panel-card" style="margin-bottom:1.25rem"><h3>Approved</h3><p style="margin:0">{{ $app->loan->principalMoney()->format() }} at {{ $app->loan->apr() }} APR {{ $app->loan->revolving ? 'is open as a line of credit since' : 'was deposited on' }} {{ $app->loan->disbursed_at->format('F j, Y') }}. <a class="plink" href="{{ route('borrow.show', $app->loan->reference) }}">View your loan</a></p></div>
@endif
<div class="panel-card">
    <dl class="kv">
        <dt>{{ $app->product->revolving ? 'Credit limit' : 'Amount' }}</dt><dd>{{ $app->money()->format() }}</dd>
        @unless ($app->product->revolving)<dt>Term</dt><dd>{{ $app->term_months }} months</dd>@endunless
        <dt>Purpose</dt><dd>{{ $app->purpose }}</dd>
        @if ($app->collateral)<dt>Equipment</dt><dd>{{ $app->collateral }}, {{ \App\Support\Money::cents($app->collateral_value)->format() }}</dd>@endif
        @if ($app->vehicle_make)<dt>Vehicle</dt><dd>{{ $app->vehicle_year }} {{ $app->vehicle_make }} {{ $app->vehicle_model }}</dd>@endif
        <dt>Deposit to</dt><dd>{{ $app->depositAccount->nickname ?? $app->depositAccount->product->name }} {{ $app->depositAccount->masked() }}</dd>
    </dl>
    @if ($app->isOpen())
    <form method="post" action="{{ route('borrow.withdraw', $app->reference) }}" style="margin-top:1rem" onsubmit="return confirm('Withdraw this application?')">@csrf<button class="linkbtn" type="submit">Withdraw application</button></form>
    @endif
</div>
@endsection
