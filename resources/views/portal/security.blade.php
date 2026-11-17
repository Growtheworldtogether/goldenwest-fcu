@extends('layouts.portal')
@section('title', 'Security')
@section('content')
@php($admin = request()->routeIs('admin.*'))
<div class="pagehead"><div><h1>Security</h1><p class="psub">{{ $admin ? 'Two-step verification is optional but recommended for staff.' : 'Keep your accounts safe.' }}</p></div></div>

@if ($enabled && ! $confirmed)
<div class="panel-card" style="margin-bottom:1.5rem">
    <h2 style="margin-bottom:.4rem">Finish setting up two-step verification</h2>
    <p class="fine" style="margin-top:0">Scan this code with an authenticator app such as Google Authenticator, 1Password or Authy, then enter the 6-digit code it shows.</p>
    <div class="two" style="align-items:center">
        <div class="qr">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <div>
            <p class="fine" style="margin-top:0">Can't scan? Enter this key instead: <code>{{ decrypt($user->two_factor_secret) }}</code></p>
            <form class="sform" method="post" action="{{ route('two-factor.confirm') }}">
                @csrf
                <label>6-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" required>@error('code', 'confirmTwoFactorAuthentication')<span class="err">{{ $message }}</span>@enderror</label>
                <button class="btn-s" type="submit">Confirm and turn on</button>
            </form>
        </div>
    </div>
</div>
@endif

<div class="panel-card flush srows">
    <div class="srow">
        <div><h3>Password</h3><p>Use at least 12 characters with upper and lower case letters and a number.</p></div>
        <div class="ctl"><button type="button" class="btn-s plain sm" data-open="dlg-password">Change password</button></div>
    </div>
    <div class="srow">
        <div><h3>Two-step verification</h3><p>@if ($confirmed)You'll be asked for a code from your authenticator app when you sign in.@else A second step at sign in, so a stolen password alone can't get in. It is optional and takes about two minutes.@endif</p></div>
        <div class="ctl">
            @if ($confirmed)
                <span class="pill on">On</span>
                <form method="post" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<button class="btn-s plain sm" type="submit">Turn off</button></form>
            @elseif ($enabled)
                <span class="pill warn">Not finished</span>
            @else
                <span class="pill off">Off</span>
                <form method="post" action="{{ route('two-factor.enable') }}">@csrf<button class="btn-s sm" type="submit">Set up two-step verification</button></form>
            @endif
        </div>
    </div>
    @if ($confirmed)
    <div class="srow">
        <div><h3>Recovery codes</h3><p>Each code works once if you lose your phone. Keep them somewhere safe.</p></div>
        <div class="ctl"><button type="button" class="btn-s plain sm" data-open="dlg-codes">Show codes</button></div>
    </div>
    @endif
    @if ($admin)
    <div class="srow"><div><h3>Staff console</h3><p>Back to your work.</p></div><div class="ctl"><a class="btn-s sm" href="{{ route('admin.home') }}">Open the console</a></div></div>
    @endif
</div>

@if ($staffAccess->count())
<h2 class="group-title">Staff accessed or acted on your account</h2>
<div class="panel-card flush">
    <p class="fine" style="margin:0;padding:1rem 1.5rem 0">Sometimes a Golden West team member signs in as you to help with a request. Each time is listed here.</p>
    <table class="dtable"><tbody>
    @foreach ($staffAccess as $s)
        <tr><td style="white-space:nowrap">{{ $s->started_at->format('M j, g:i a') }}</td><td><strong>{{ $s->admin->name }}</strong>, {{ $s->admin->adminTitle() }}<small>{{ $staffReasons[$s->reason_code] ?? $s->reason_code }}</small></td></tr>
    @endforeach
    </tbody></table>
</div>
@endif

<h2 class="group-title">Recent security activity</h2>
<div class="panel-card flush">
    @if ($events->count())
    <table class="dtable"><tbody>
    @foreach ($events as $e)
        <tr><td style="white-space:nowrap">{{ $e->created_at->format('M j, g:i a') }}</td><td>{{ ['login.success' => 'Signed in', 'login.failed' => 'Failed sign-in attempt', 'password.changed' => 'Password changed', 'password.reset' => 'Password reset', 'profile.updated' => 'Contact details updated', 'two_factor.enabled' => 'Two-step verification turned on', 'two_factor.disabled' => 'Two-step verification turned off'][$e->action] ?? $e->action }}</td><td class="num hide-s" style="font-weight:400;color:var(--muted)">{{ $e->ip }}</td></tr>
    @endforeach
    </tbody></table>
    @else
        <div class="empty"><b>Nothing yet</b>Sign-ins and security changes will appear here.</div>
    @endif
</div>
<p class="fine">Don't recognize something? Call Customer Care at <a class="plink" href="tel:8005550147">800-555-0147</a> right away.</p>

<dialog id="dlg-password">
    <div class="dh"><h3>Change your password</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('user-password.update') }}">
        @csrf @method('PUT')
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password">@error('current_password', 'updatePassword')<span class="err">{{ $message }}</span>@enderror</label>
        <label>New password<input type="password" name="password" required autocomplete="new-password" minlength="12">@error('password', 'updatePassword')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button class="btn-s" type="submit">Update password</button>
    </form>
</dialog>
@if ($confirmed)
<dialog id="dlg-codes">
    <div class="dh"><h3>Recovery codes</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <ul class="codes">@foreach ($recoveryCodes as $c)<li>{{ $c }}</li>@endforeach</ul>
    <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<button class="btn-s plain sm" type="submit">Get new recovery codes</button></form>
</dialog>
@endif

@push('scripts')
<script>
document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
document.querySelectorAll('dialog').forEach(function (d) {
    d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
});
@if ($errors->updatePassword->any()) document.getElementById('dlg-password').showModal(); @endif
</script>
@endpush
@endsection
