@extends('layouts.portal')
@section('title', 'Security')
@section('content')
<h1>Security</h1>
<p class="psub">Keep your accounts safe with a second step at sign in.</p>

<div class="two">
<div class="panel-card">
    <h2>Two-step verification</h2>
    @if ($confirmed)
        <p>Two-step verification is <strong>on</strong>. You will be asked for a code from your authenticator app when you sign in.</p>
        <h3 style="font-size:1rem;margin:1rem 0 .3rem">Recovery codes</h3>
        <p class="fine">Keep these somewhere safe. Each one works once if you lose your phone.</p>
        <ul class="codes">@foreach ($recoveryCodes as $c)<li>{{ $c }}</li>@endforeach</ul>
        <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}" style="display:inline">@csrf<button class="plink" style="background:none;border:0;cursor:pointer;font:inherit;font-weight:700">Get new recovery codes</button></form>
        <form method="post" action="{{ route('two-factor.disable') }}" style="margin-top:1rem">@csrf @method('DELETE')<button class="btn" type="submit" style="background:#fff;border:2px solid var(--navy);color:var(--navy)">Turn off</button></form>
    @elseif ($enabled)
        <p>Scan this code with an authenticator app such as Google Authenticator, 1Password or Authy, then enter the 6-digit code it shows.</p>
        <div class="qr">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <p class="fine">Can't scan? Enter this key instead: <code>{{ decrypt($user->two_factor_secret) }}</code></p>
        <form class="form" method="post" action="{{ route('two-factor.confirm') }}">
            @csrf
            <label>6-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" required>@error('code', 'confirmTwoFactorAuthentication')<span class="err">{{ $message }}</span>@enderror</label>
            <button class="btn" type="submit">Confirm and turn on</button>
        </form>
    @else
        <p>Add a second step so a stolen password alone cannot get into your accounts. It takes about two minutes.</p>
        <form method="post" action="{{ route('two-factor.enable') }}">@csrf<button class="btn" type="submit">Set up two-step verification</button></form>
    @endif
</div>

<div class="panel-card">
    <h2>Change your password</h2>
    <form class="form" method="post" action="{{ route('user-password.update') }}">
        @csrf @method('PUT')
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password">@error('current_password', 'updatePassword')<span class="err">{{ $message }}</span>@enderror</label>
        <label>New password<input type="password" name="password" required autocomplete="new-password" minlength="12">@error('password', 'updatePassword')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button class="btn" type="submit">Update password</button>
    </form>
</div>
</div>

@if ($staffAccess->count())
<div class="panel-card">
    <h2>Staff accessed or acted on your account</h2>
    <p class="fine" style="margin-top:0">Sometimes a Golden West team member signs in as you to help with a request. Each time is listed here.</p>
    <table class="txtable"><tbody>
    @foreach ($staffAccess as $s)
        <tr><td style="white-space:nowrap;width:1%">{{ $s->started_at->format('M j, g:i a') }}</td><td>{{ $s->admin->name }}, {{ $s->admin->adminTitle() }}<span class="sub">{{ $staffReasons[$s->reason_code] ?? $s->reason_code }}</span></td></tr>
    @endforeach
    </tbody></table>
</div>
@endif

<div class="panel-card">
    <h2>Recent security activity</h2>
    @if ($events->count())
        <table class="txtable"><tbody>
        @foreach ($events as $e)
            <tr><td style="white-space:nowrap;width:1%">{{ $e->created_at->format('M j, g:i a') }}</td><td>{{ ['login.success' => 'Signed in', 'login.failed' => 'Failed sign-in attempt', 'password.changed' => 'Password changed', 'password.reset' => 'Password reset', 'profile.updated' => 'Contact details updated', 'two_factor.enabled' => 'Two-step verification turned on', 'two_factor.disabled' => 'Two-step verification turned off'][$e->action] ?? $e->action }}@if ($e->ip)<span class="sub">{{ $e->ip }}</span>@endif</td></tr>
        @endforeach
        </tbody></table>
    @else
        <p style="margin:0">Nothing to show yet.</p>
    @endif
    <p class="fine" style="margin-top:1rem">Don't recognize something? Call Customer Care at <a class="plink" href="tel:8005550147">800-555-0147</a> right away.</p>
</div>
@endsection
