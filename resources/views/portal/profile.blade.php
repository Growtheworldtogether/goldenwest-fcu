@extends('layouts.portal')
@section('title', 'Profile')
@section('content')
<h1>Profile and alerts</h1>
<p class="psub">Member since {{ $user->profile?->membership_opened_at?->format('F Y') ?? $user->created_at->format('F Y') }}</p>

<div class="table-title" style="margin-top:0"><h2>Personal information</h2><button type="button" class="linkbtn" data-open="dlg-contact">Edit phone and address</button></div>
<div class="panel-card flush">
    <dl class="kvlist">
        <dt>Name</dt><dd>{{ $user->name }}</dd>
        <dt>Email</dt><dd>{{ $user->email }}</dd>
        <dt>Member number</dt><dd>{{ $user->member_number }}</dd>
        <dt>Mobile phone</dt><dd>{{ $user->phone }}</dd>
        <dt>Home address</dt><dd>{{ $user->address_line1 }}, {{ $user->city }}, {{ $user->state }} {{ $user->postal_code }}</dd>
    </dl>
    <p class="fine" style="margin:0;padding:0 1.25rem 1.1rem">To change your name or email, call Customer Care at 800-555-0147 so we can verify it is you.</p>
</div>

<div class="table-title"><h2>Alerts</h2></div>
<form method="post" action="{{ route('profile.notifications') }}" class="panel-card flush">
    @csrf @method('PUT')
    <table class="dtable alerttable">
        <thead><tr><th>Alert</th><th>Email</th><th>Text message</th></tr></thead>
        <tbody>
        @foreach ([['email_transactions', 'Deposits, transfers and payments', 'email'], ['sms_alerts', 'Large transactions and sign-ins', 'text'], ['email_marketing', 'Offers and rates', 'email']] as [$key, $label, $channel])
            <tr>
                <td>{{ $label }}</td>
                <td>@if ($channel === 'email')<input type="checkbox" name="{{ $key }}" value="1" @checked($prefs[$key]) aria-label="{{ $label }} by email">@else <span aria-hidden="true">&ndash;</span>@endif</td>
                <td>@if ($channel === 'text')<input type="checkbox" name="{{ $key }}" value="1" @checked($prefs[$key]) aria-label="{{ $label }} by text">@else <span aria-hidden="true">&ndash;</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="searchbar" style="border-top:1px solid var(--line);border-bottom:0"><span class="fine" style="margin:0">Security alerts, such as a new device sign-in, are always sent.</span><button class="btn-s" type="submit" style="margin-left:auto">Save alert settings</button></div>
</form>

<dialog id="dlg-contact">
    <div class="dh"><h3>Edit phone and address</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('user-profile-information.update') }}">
        @csrf @method('PUT')
        <label>Mobile phone<input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" required>@error('phone', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Street address<input name="address_line1" value="{{ old('address_line1', $user->address_line1) }}" required>@error('address_line1', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        <label>City<input name="city" value="{{ old('city', $user->city) }}" required>@error('city', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        <div class="two2">
            <label>State<input name="state" value="{{ old('state', $user->state) }}" maxlength="2" required style="text-transform:uppercase">@error('state', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
            <label>ZIP<input name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" required>@error('postal_code', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <button class="btn-s" type="submit">Save changes</button>
    </form>
</dialog>

@push('scripts')
<script>
document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
document.querySelectorAll('dialog').forEach(function (d) {
    d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
});
@if ($errors->updateProfileInformation->any()) document.getElementById('dlg-contact').showModal(); @endif
</script>
@endpush
@endsection
