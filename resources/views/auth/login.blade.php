@extends('layouts.auth')
@section('title', 'Log in | '.brand('name'))
@section('heading', 'Log in')
@section('lede', 'Access your accounts securely, 24 hours a day.')
@section('form')
<form class="form" method="post" action="{{ route('login.store') }}">
    @csrf
    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus>@error('email')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Password<input type="password" name="password" required autocomplete="current-password">@error('password')<span class="err">{{ $message }}</span>@enderror</label>
    <label style="grid-template-columns:auto 1fr;align-items:center;gap:.6rem;font-weight:400"><input type="checkbox" name="remember" value="1" style="min-height:0"> Keep me signed in on this device</label>
    <button class="btn" type="submit">Log in</button>
</form>
<p class="fine" style="margin-top:1.25rem"><a href="{{ route('password.request') }}" style="color:var(--blue)">Forgot your password?</a> &nbsp;|&nbsp; New here? <a href="{{ route('register') }}" style="color:var(--blue)">Become a member</a></p>
<p class="fine">{{ brand('short_name') }} will never ask for your password or a one-time code by phone, text or email.</p>
@endsection
