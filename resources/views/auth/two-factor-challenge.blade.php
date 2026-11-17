@extends('layouts.auth')
@section('title', 'Verify it is you | '.brand('name'))
@section('heading', 'Verify it is you')
@section('lede', 'Enter the 6-digit code from your authenticator app, or use a recovery code.')
@section('form')
<form class="form" method="post" action="{{ route('two-factor.login.store') }}">
    @csrf
    <label>Authentication code<input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>@error('code')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Or a recovery code<input name="recovery_code" autocomplete="off">@error('recovery_code')<span class="err">{{ $message }}</span>@enderror</label>
    <button class="btn" type="submit">Continue</button>
</form>
@endsection
