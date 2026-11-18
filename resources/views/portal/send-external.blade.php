@extends('layouts.portal')
@section('title', 'Send to another bank')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('transfers.create') }}">&larr; Transfer</a></p>
<h1>Send money to another bank</h1>
<p class="psub">Sent by ACH. It arrives the next business day. Large amounts may be reviewed first.</p>
@endsection
@section('content')
@if ($payees->isEmpty())
    <div class="panel-card"><p style="margin:0">First add the account you want to send to. <a class="plink" href="{{ route('payees.index') }}">Add an external account</a></p></div>
@else
<div class="panel-card">
    <form class="form" method="post" action="{{ route('transfers.external.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>From
            <select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }} - available {{ $a->available_balance->format() }}</option>@endforeach</select>
            @error('account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>To
            <select name="payee_id" required>@foreach ($payees as $p)<option value="{{ $p->id }}" @selected((int) old('payee_id') === $p->id)>{{ $p->label() }}@unless ($p->isActive()) (available {{ $p->activates_at->timezone(config('app.timezone'))->format('M j, g:i a') }})@endunless</option>@endforeach</select>
            @error('payee_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required autocomplete="off">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Memo (optional)<input name="memo" value="{{ old('memo') }}" maxlength="140">@error('memo')<span class="err">{{ $message }}</span>@enderror</label>
        @include('portal._staff-code')
        <button class="btn" type="submit">Send money</button>
    </form>
</div>
@endif
@endsection
