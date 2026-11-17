@extends('layouts.portal')
@section('title', 'Application '.$app->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('admin.loans.index') }}">&larr; Loans</a></p>
<h1>{{ $app->product->name }}: {{ $app->money()->format() }}</h1>
<p class="psub">{{ $app->reference }} &middot; {{ $app->user->name }} &middot; <span class="tag">{{ $app->statusLabel() }}</span></p>
@endsection
@section('content')
<div class="cols tcols">
<div>
    <div class="panel-card">
        <h3>Application</h3>
        <dl class="kv">
            <dt>Member</dt><dd><a class="plink" href="{{ route('admin.members.show', $app->user) }}">{{ $app->user->name }}</a> #{{ $app->user->member_number }} &middot; ID {{ $app->user->kyc_status }}</dd>
            <dt>Amount</dt><dd>{{ $app->money()->format() }} over {{ $app->term_months }} months</dd>
            <dt>Purpose</dt><dd>{{ $app->purpose }}</dd>
            <dt>Income</dt><dd>{{ \App\Support\Money::cents($app->annual_income)->format() }} a year, {{ str_replace('_', '-', $app->employment) }}</dd>
            @if ($app->vehicle_make)<dt>Vehicle</dt><dd>{{ $app->vehicle_year }} {{ $app->vehicle_make }} {{ $app->vehicle_model }}, {{ \App\Support\Money::cents($app->vehicle_value)->format() }}</dd>@endif
            <dt>Deposit to</dt><dd>{{ $app->depositAccount->nickname ?? $app->depositAccount->product->name }} {{ $app->depositAccount->masked() }}</dd>
            <dt>Rate range</dt><dd>{{ $app->product->aprRange() }}</dd>
        </dl>
    </div>
    @if ($app->info_request)
    <div class="panel-card" style="margin-top:1.25rem"><h3>Question sent</h3><p style="white-space:pre-line;margin-top:0">{{ $app->info_request }}</p><p style="margin-bottom:0"><strong>Member's answer:</strong> {{ $app->member_reply ?? 'Not yet answered.' }}</p></div>
    @endif
    @foreach ($app->decisions as $dec)
    <div class="panel-card" style="margin-top:1.25rem"><h3>Decision: {{ $dec->decision }}</h3><p style="margin:0" class="fine">By {{ $dec->decider->name }} on {{ $dec->created_at->format('M j, g:i a') }} {{ $dec->reason_text }}</p></div>
    @endforeach
</div>
<aside>
@if ($app->isOpen())
    <div class="panel-card">
        <h3>Approve and pay out</h3>
        <form class="sform" method="post" action="{{ route('admin.loans.act', $app->reference) }}">@csrf<input type="hidden" name="action" value="approve">
            <label>APR (%)<input name="apr" inputmode="decimal" required placeholder="{{ number_format($app->product->apr_min_bps / 100, 2) }}">@error('apr')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Amount ($)<input name="amount" inputmode="decimal" value="{{ number_format($app->amount / 100, 2, '.', '') }}">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
            <button type="submit" class="gold">Approve and deposit</button>
        </form>
    </div>
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Ask the member</h3>
        <form class="sform" method="post" action="{{ route('admin.loans.act', $app->reference) }}">@csrf<input type="hidden" name="action" value="info">
            <label>Question<textarea name="info_request" rows="3" maxlength="1000" required></textarea>@error('info_request')<span class="err">{{ $message }}</span>@enderror</label>
            <button type="submit" class="plain">Send question</button>
        </form>
    </div>
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Decline</h3>
        <form class="sform" method="post" action="{{ route('admin.loans.act', $app->reference) }}">@csrf<input type="hidden" name="action" value="reject">
            <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Message to the member<textarea name="reason_text" rows="3" maxlength="500" required></textarea>@error('reason_text')<span class="err">{{ $message }}</span>@enderror</label>
            <button type="submit" class="plain">Decline application</button>
        </form>
    </div>
@elseif ($app->loan)
    <div class="panel-card"><h3>Loan</h3><p style="margin:0"><a class="plink" href="{{ route('admin.loans.loan', $app->loan->reference) }}">{{ $app->loan->reference }}</a> at {{ $app->loan->apr() }} APR</p></div>
@endif
</aside>
</div>
@endsection
