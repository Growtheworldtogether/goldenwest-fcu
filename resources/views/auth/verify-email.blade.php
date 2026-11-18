@extends('layouts.auth')
@section('title', 'Verify your email | '.brand('name'))
@section('heading', 'Check your email')
@section('lede', 'We sent a link to confirm your email address. Open it to finish setting up your account.')
@section('form')
<form class="form" method="post" action="{{ route('verification.send') }}">
    @csrf
    <button class="btn" type="submit">Send the link again</button>
</form>
<form method="post" action="{{ route('logout') }}" style="margin-top:1rem">@csrf<button type="submit" class="fine" style="background:none;border:0;color:var(--blue);cursor:pointer;padding:0;font:inherit">Log out</button></form>
@endsection
