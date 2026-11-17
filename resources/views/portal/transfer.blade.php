@extends('layouts.portal')
@section('title', 'Transfer')
@section('content')
<h1>Transfer money</h1>
<p class="psub">Move money between your own Golden West accounts. It is free and takes effect right away.</p>
@if ($accounts->count() < 2)
    <div class="panel-card"><p style="margin:0">You need at least two accounts to transfer between them. <a class="plink" href="{{ route('accounts.create') }}">Open another account</a>, it is free.</p></div>
@else
<div class="panel-card">
    <form class="form" method="post" action="{{ route('transfers.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>From
            <select name="from_account_id" required>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('from_account_id', request('from')) === $a->id)>{{ $a->nickname }} {{ $a->masked() }} - available {{ $a->available_balance->format() }}</option>@endforeach
            </select>
            @error('from_account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>To
            <select name="to_account_id" required>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('to_account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach
            </select>
            @error('to_account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required autocomplete="off">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Memo (optional)<input name="memo" value="{{ old('memo') }}" maxlength="140">@error('memo')<span class="err">{{ $message }}</span>@enderror</label>
        @if (session('impersonation'))
            <label>Your staff code<input name="admin_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="6-digit code from your authenticator app">@error('admin_code')<span class="err">{{ $message }}</span>@enderror</label>
        @endif
        <button class="btn" type="submit">Transfer</button>
    </form>
    <p class="fine" style="margin-top:1rem">Large transfers may be reviewed before they are completed. If so, the money is set aside right away and we show you the status.</p>
</div>
@endif
@endsection
