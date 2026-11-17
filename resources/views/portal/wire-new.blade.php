@extends('layouts.portal')
@section('title', 'Send a wire')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('wires.index') }}">&larr; Wire transfers</a></p>
<h1>Send a wire</h1>
<p class="psub">Fee {{ $fee->format() }}. Requested now, it is sent {{ $processOn->isToday() ? 'today' : 'on '.$processOn->format('l, F j') }} once approved. The cutoff for same-day wires is {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $cutoff)->format('g:i a') }} Pacific time.</p>
@endsection
@section('content')
@if (! $verified)
    <div class="notice-bar" role="status"><p>You need to verify your identity before you can send a wire.</p><p><a class="btn" href="{{ route('kyc.show') }}">Verify my identity</a></p></div>
@elseif ($payees->isEmpty())
    <div class="panel-card"><p style="margin:0">First add the account you want to wire to. <a class="plink" href="{{ route('payees.index') }}">Add an external account</a>. A new account can be used {{ (int) config('bank.payee_cooling_hours') }} hours after you add it.</p></div>
@else
<div class="panel-card">
    <form class="form" method="post" action="{{ route('wires.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>From
            <select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }} - available {{ $a->available_balance->format() }}</option>@endforeach</select>
            @error('account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>To
            <select name="payee_id" required>@foreach ($payees as $p)<option value="{{ $p->id }}" @selected((int) old('payee_id') === $p->id)>{{ $p->name }}, {{ $p->label() }}@unless ($p->isActive()) (available {{ $p->activates_at->timezone('America/Los_Angeles')->format('M j, g:i a') }})@endunless</option>@endforeach</select>
            @error('payee_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required autocomplete="off">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Recipient's address<input name="recipient_address" value="{{ old('recipient_address') }}" maxlength="200" required autocomplete="off" placeholder="Street, city, state, ZIP">@error('recipient_address')<span class="err">{{ $message }}</span>@enderror</label>
        <label>What is the wire for?<input name="purpose" value="{{ old('purpose') }}" maxlength="140" required>@error('purpose')<span class="err">{{ $message }}</span>@enderror</label>
        <label class="chk" style="display:flex;gap:.6rem;align-items:flex-start"><input type="checkbox" name="understand" value="1" @checked(old('understand')) style="margin-top:.3rem"><span>I understand a wire cannot be taken back once it is sent, and that the {{ $fee->format() }} fee is charged with it.</span></label>
        @error('understand')<span class="err">{{ $message }}</span>@enderror
        @include('portal._staff-code')
        <button class="btn" type="submit">Request wire</button>
    </form>
    <p class="fine" style="margin-top:1rem">We will never call you to ask you to send a wire. If someone asks you to, hang up and call Customer Care at {{ brand('support_phone') }}.</p>
</div>
@endif
@endsection
