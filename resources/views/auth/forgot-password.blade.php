@extends('layouts.auth')
@section('title', 'Reset your password | '.brand('name'))
@section('heading', 'Reset your password')
@section('lede', 'Enter the email address on your account and we will send you a link that expires in one hour.')
@section('form')
<form class="form" method="post" action="{{ route('password.email') }}">
    @csrf
    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
    <button class="btn" type="submit">Send reset link</button>
</form>
<p class="fine" style="margin-top:1.25rem"><a href="{{ route('login') }}" style="color:var(--blue)">Back to log in</a></p>
@endsection
