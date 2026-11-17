@extends('layouts.portal')
@section('title', 'Send crypto')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('crypto.index') }}">&larr; Crypto wallet</a></p>
<h1>Send crypto</h1>
<p class="psub">Every send is reviewed by a person before it goes out.</p>
@endsection
@section('content')
@php($w = $wallets->get($asset->id))
<nav class="tabs" aria-label="Asset">
    @foreach ($assets as $a)<a href="{{ route('crypto.send', ['asset' => $a->id]) }}" @if ($a->id === $asset->id) aria-current="page" @endif>{{ $a->symbol }}@if ($a->symbol === 'USDC' || $a->symbol === 'USDT') <small>({{ $a->network }})</small>@endif</a>@endforeach
</nav>
<form class="panel-card form" method="post" action="{{ route('crypto.send.store') }}" autocomplete="off" style="max-width:36rem">
    @csrf
    <input type="hidden" name="asset" value="{{ $asset->id }}"><input type="hidden" name="token" value="{{ $token }}">
    <p style="margin:0">Available: <b>{{ $asset->format($w?->available() ?? 0) }}</b>. Network fee: <b>{{ $asset->format($asset->network_fee) }}</b>, taken on top of the amount.</p>
    <label>Send to ({{ $asset->network }} address)<input name="address" value="{{ old('address') }}" maxlength="120" spellcheck="false" required>@error('address')<span class="err">{{ $message }}</span>@enderror</label>
    @if (config("crypto.networks.{$asset->network}.memo"))
        <label>Memo or tag (if the receiver asked for one)<input name="memo" value="{{ old('memo') }}" maxlength="60">@error('memo')<span class="err">{{ $message }}</span>@enderror</label>
    @endif
    <label>Amount ({{ $asset->symbol }})<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required>@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
    <label class="chk" style="display:flex;gap:.6rem;align-items:flex-start"><input type="checkbox" name="understand" value="1" @checked(old('understand')) style="margin-top:.3rem"><span>I checked the address and the network. I understand a crypto send cannot be taken back once it goes out.</span></label>
    @error('understand')<span class="err">{{ $message }}</span>@enderror
    @if (session('impersonation'))
        @include('portal._staff-code')
    @else
        <label>Your password<input type="password" name="password" autocomplete="current-password" required>@error('password')<span class="err">{{ $message }}</span>@enderror</label>
    @endif
    <div><button class="btn" type="submit">Request send</button></div>
</form>
@include('portal._crypto-note')
@endsection
