@extends('layouts.auth')
@section('title', 'Security question | '.brand('name'))
@section('heading', 'Answer your security question')
@section('lede', $risky ? 'This sign in looks different from your usual ones, so we also need the code we just emailed to '.$email.'.' : 'One quick question to confirm it is you.')
@section('form')
<form class="form" method="post" action="{{ route('security.check.verify') }}" autocomplete="off">
    @csrf
    <label>{{ $question }}<input name="answer" type="password" autocomplete="off" autofocus required>@error('answer')<span class="err">{{ $message }}</span>@enderror</label>
    @if ($risky)
    <label>Code from your email<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>@error('code')<span class="err">{{ $message }}</span>@enderror</label>
    @endif
    <button class="btn" type="submit">Continue</button>
</form>
@if ($risky)
<form method="post" action="{{ route('security.check.resend') }}" style="margin-top:.75rem">@csrf<button class="linkbtn" type="submit">Send a new code</button></form>
@endif
<form method="post" action="{{ route('logout') }}" style="margin-top:.5rem">@csrf<button class="linkbtn" type="submit">Cancel and sign out</button></form>
@endsection
