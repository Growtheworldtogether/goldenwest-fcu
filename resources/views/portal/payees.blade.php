@extends('layouts.portal')
@section('title', 'External accounts')
@section('band')
<h1>External accounts</h1>
<p class="psub">Accounts at other banks you send money to or pull money from. A new account can be used {{ $hours }} hours after you add it.</p>
@endsection
@section('content')
<div class="table-title" style="margin-top:0"><h2>Your external accounts</h2><a class="linkbtn" href="{{ route('transfers.external') }}">Send money to one</a></div>
<div class="panel-card flush">
    @if ($payees->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Name</th><th>Account</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($payees as $p)
            <tr>
                <td>{{ $p->name }}@if ($p->bank_name)<small>{{ $p->bank_name }}</small>@endif</td>
                <td>{{ ucfirst($p->account_type) }} ending {{ $p->last4 }}</td>
                <td>@if ($p->isActive())<span class="chip completed">Ready to use</span>@else<span class="chip pending">Available {{ $p->activates_at->timezone(config('app.timezone'))->format('M j, g:i a') }} PT</span>@endif</td>
                <td class="go"><form method="post" action="{{ route('payees.destroy', $p) }}" onsubmit="return confirm('Remove this account?')">@csrf @method('DELETE')<button type="submit" class="linkbtn">Remove</button></form></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>No external accounts yet</b>Add one below to send or receive money at another bank.</div>
    @endif
</div>

<div class="table-title"><h2>Add an external account</h2></div>
<div class="panel-card">
    <form class="form" method="post" action="{{ route('payees.store') }}" autocomplete="off">
        @csrf
        <label>Name on the account<input name="name" value="{{ old('name') }}" required maxlength="120">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Bank name (optional)<input name="bank_name" value="{{ old('bank_name') }}" maxlength="120">@error('bank_name')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Routing number<input name="routing_number" value="{{ old('routing_number') }}" inputmode="numeric" maxlength="9" required>@error('routing_number')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Account number<input name="account_number" inputmode="numeric" maxlength="17" required>@error('account_number')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Account number again<input name="account_number_confirmation" inputmode="numeric" maxlength="17" required></label>
        <label>Account type<select name="account_type"><option value="checking">Checking</option><option value="savings">Savings</option></select></label>
        @include('portal._staff-code')
        <button class="btn" type="submit">Save account</button>
    </form>
    <p class="fine" style="margin-top:1rem">We never show the full account number again after you save it.</p>
</div>
@endsection
