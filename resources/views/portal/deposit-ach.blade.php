@extends('layouts.portal')
@section('title', 'From another bank')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('deposits.index') }}">&larr; Deposit</a></p>
<h1>Add money from another bank</h1>
<p class="psub">Up to {{ $limit->format() }} at a time. We review the request and the money arrives the next business day after it is approved.</p>
@endsection
@section('content')
@if (! $verified)
    <div class="notice-bar" role="status"><p>You need to verify your identity before you can move money from another bank.</p><p><a class="btn" href="{{ route('kyc.show') }}">Verify my identity</a></p></div>
@elseif ($payees->isEmpty())
    <div class="panel-card"><p style="margin:0">First add the account at the other bank. <a class="plink" href="{{ route('payees.index') }}">Add an external account</a></p></div>
@else
<div class="panel-card">
    <form class="form" method="post" action="{{ route('deposits.ach.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>From
            <select name="payee_id" required>@foreach ($payees as $p)<option value="{{ $p->id }}" @selected((int) old('payee_id') === $p->id)>{{ $p->label() }}@unless ($p->isActive()) (available {{ $p->activates_at->timezone('America/Los_Angeles')->format('M j, g:i a') }})@endunless</option>@endforeach</select>
            @error('payee_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Into
            <select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select>
            @error('account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required autocomplete="off">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        @include('portal._staff-code')
        <button class="btn" type="submit">Request transfer</button>
    </form>
    <p class="fine" style="margin-top:1rem">New external accounts can be used {{ (int) config('bank.payee_cooling_hours') }} hours after you add them.</p>
</div>
@endif
@endsection
