@extends('layouts.auth')
@section('title', 'Confirm your password | '.brand('name'))
@section('heading', 'Confirm your password')
@section('lede', 'For your security, please confirm your password to continue.')
@section('form')
<form class="form" method="post" action="{{ route('password.confirm.store') }}">
    @csrf
    <label>Password<input type="password" name="password" required autofocus autocomplete="current-password">@error('password')<span class="err">{{ $message }}</span>@enderror</label>
    <button class="btn" type="submit">Confirm</button>
</form>
@endsection
