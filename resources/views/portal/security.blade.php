@extends('layouts.portal')
@section('title', 'Security')
@php($admin = request()->routeIs('admin.*'))
@section('band')
<h1>Security center</h1>
<p class="psub">{{ $admin ? 'Two-step verification is optional but recommended for staff.' : 'Manage how you sign in and review recent activity on your profile.' }}</p>
@endsection
@section('content')

@if (! $confirmed && ! $enabled)
    <div class="notice-bar" role="status"><p><strong>Two-step verification is off.</strong> Add a second step at sign in so a stolen password alone can't get into your account.</p></div>
@endif

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

<div class="panel-card flush">
    <div class="grouphead"><h2>Sign-in and security</h2></div>
    <table class="dtable">
        <thead><tr><th>Setting</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <tr>
                <td><strong>Password</strong><small>At least 12 characters with upper and lower case letters and a number.</small></td>
                <td>Set</td>
                <td class="go"><button type="button" class="linkbtn" data-open="dlg-password">Change password</button></td>
            </tr>
            <tr>
                <td><strong>Two-step verification</strong><small>A code from your authenticator app, asked for at sign in. Optional.</small></td>
                <td>{{ $confirmed ? 'On' : ($enabled ? 'Not finished' : 'Off') }}</td>
                <td class="go">
                    @if ($confirmed)
                        <form method="post" action="{{ route('two-factor.disable') }}" style="display:inline">@csrf @method('DELETE')<button class="linkbtn" type="submit">Turn off</button></form>
                    @elseif (! $enabled)
                        <form method="post" action="{{ route('two-factor.enable') }}" style="display:inline">@csrf<button class="linkbtn" type="submit">Set up two-step verification</button></form>
                    @endif
                </td>
            </tr>
            @if ($confirmed)
            <tr>
                <td><strong>Recovery codes</strong><small>Each works once if you lose your phone. Keep them somewhere safe.</small></td>
                <td>Available</td>
                <td class="go"><button type="button" class="linkbtn" data-open="dlg-codes">Show codes</button></td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

@unless ($admin)
<div class="table-title"><h2>Where you are signed in</h2></div>
<div class="panel-card flush">
    <table class="dtable"><tbody>
    @foreach ($sessions as $d)
        <tr><td><strong>{{ $d->device }}</strong>@if ($d->current) <span class="chip completed">This device</span>@endif<small>{{ $d->ip }} &middot; last active {{ $d->last->diffForHumans() }}</small></td></tr>
    @endforeach
    </tbody></table>
    @if ($sessions->count() > 1)
    <form class="sform" method="post" action="{{ route('security.signout') }}" style="padding:1rem 1.25rem;border-top:1px solid #e7ebf6">@csrf
        <label>Password<input type="password" name="password" autocomplete="current-password" required>@error('password', 'devices')<span class="err">{{ $message }}</span>@enderror</label>
        <button class="plain" type="submit">Sign out of other devices</button>
    </form>
    @endif
</div>

<div class="table-title"><h2>Lock my accounts</h2></div>
<div class="panel-card">
    @if ($selfLocked)
        <p style="margin:0"><strong>Your accounts are locked.</strong> No money can move in or out. Call Customer Care at {{ brand('support_phone') }} and we will check it is you before unlocking.</p>
    @else
        <p class="fine" style="margin-top:0">If you think someone has your details, lock every account now. No money can move in or out until we speak with you and unlock them.</p>
        <form class="sform" method="post" action="{{ route('security.lock') }}" onsubmit="return confirm('Lock all of your accounts now?')">@csrf
            <label>Password<input type="password" name="password" autocomplete="current-password" required>@error('password', 'lock')<span class="err">{{ $message }}</span>@enderror</label>
            <button class="plain" type="submit">Lock all my accounts</button>
        </form>
    @endif
</div>
@endunless

<div class="table-title"><h2>Recent sign-in and security activity</h2></div>
<div class="panel-card flush">
    @if ($events->count())
    <table class="dtable">
        <thead><tr><th>Date</th><th>Activity</th><th class="hide-s">From</th></tr></thead>
        <tbody>
        @foreach ($events as $e)
            <tr><td style="white-space:nowrap">{{ $e->created_at->format('M j, Y g:i a') }}</td><td>{{ ['login.success' => 'Signed in', 'login.failed' => 'Failed sign-in attempt', 'password.changed' => 'Password changed', 'password.reset' => 'Password reset', 'profile.updated' => 'Contact details updated', 'two_factor.enabled' => 'Two-step verification turned on', 'two_factor.disabled' => 'Two-step verification turned off'][$e->action] ?? $e->action }}</td><td class="hide-s">{{ $e->ip }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @else
        <div class="empty"><b>Nothing yet</b>Sign-ins and security changes will appear here.</div>
    @endif
</div>

@if ($staffAccess->count())
<div class="table-title"><h2>Staff accessed or acted on your account</h2></div>
<div class="panel-card flush">
    <p class="fine" style="margin:0;padding:1rem 1.25rem 0">Sometimes a {{ brand('short_name') }} team member signs in as you to help with a request. Each time is listed here.</p>
    <table class="dtable">
        <tbody>
        @foreach ($staffAccess as $s)
            <tr><td style="white-space:nowrap">{{ $s->started_at->format('M j, Y g:i a') }}</td><td><strong>{{ $s->admin->name }}</strong>, {{ $s->admin->adminTitle() }}<small>{{ $staffReasons[$s->reason_code] ?? $s->reason_code }}</small></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
<p class="fine" style="margin-top:1.25rem">Don't recognize something? Call Customer Care at <a class="plink" href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a> right away.</p>

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
