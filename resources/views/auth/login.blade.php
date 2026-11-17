@extends('layouts.site')
@section('title', 'Log in | Golden West Federal Credit Union')

@section('content')
<section class="sec alt" style="min-height:60vh">
    <div class="wrap">
        <div class="authbox">
            <h2>Log in</h2>
            <p class="fine">Access your accounts securely, 24 hours a day.</p>
            <form class="form" method="post" action="{{ url('/login') }}">
                @csrf
                <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus>@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                <label>Password<input type="password" name="password" required autocomplete="current-password">@error('password')<span class="err">{{ $message }}</span>@enderror</label>
                <button class="btn" type="submit">Log in</button>
            </form>
            <p class="fine" style="margin-top:1.25rem">Golden West will never ask for your password or a one-time code by phone, text or email. New to online banking? <a href="{{ url('/join') }}" style="color:var(--blue)">Become a member</a>. Need help? <a href="{{ url('/help#password') }}" style="color:var(--blue)">Reset your password</a>.</p>
        </div>
    </div>
</section>
@endsection
