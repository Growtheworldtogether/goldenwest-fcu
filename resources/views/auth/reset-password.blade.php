@extends('layouts.auth')
@section('title', 'Choose a new password | Golden West Federal Credit Union')
@section('heading', 'Choose a new password')
@section('form')
<form class="form" method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <label>Email<input type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
    <label>New password<input type="password" name="password" required autocomplete="new-password" minlength="12" autofocus>@error('password')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    <p class="fine" style="margin:0">At least 12 characters, with upper and lower case letters and a number.</p>
    <button class="btn" type="submit">Save password</button>
</form>
@endsection
