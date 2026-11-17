@extends('layouts.portal')
@section('title', 'Profile')
@section('content')
<div class="pagehead"><div><h1>Profile</h1><p class="psub">Member since {{ $user->profile?->membership_opened_at?->format('F Y') ?? $user->created_at->format('F Y') }}</p></div></div>

<div class="panel-card flush srows">
    <div class="srow"><div><h3>Name</h3></div><div class="val">{{ $user->name }}</div></div>
    <div class="srow"><div><h3>Email</h3><p>To change your name or email, call Customer Care so we can verify it is you.</p></div><div class="val">{{ $user->email }}</div></div>
    <div class="srow"><div><h3>Member number</h3></div><div class="val">{{ $user->member_number }}</div></div>
    <div class="srow"><div><h3>Mobile phone</h3></div><div class="ctl"><span class="val">{{ $user->phone }}</span></div></div>
    <div class="srow"><div><h3>Home address</h3></div><div class="ctl"><span class="val">{{ $user->address_line1 }}<br>{{ $user->city }}, {{ $user->state }} {{ $user->postal_code }}</span><button type="button" class="btn-s plain sm" data-open="dlg-contact">Edit</button></div></div>
</div>

<h2 class="group-title">Notifications</h2>
<form method="post" action="{{ route('profile.notifications') }}" class="panel-card flush srows">
    @csrf @method('PUT')
    @foreach (['email_transactions' => ['Deposits, transfers and payments', 'Email me when money moves in or out.'], 'sms_alerts' => ['Large transactions and sign-ins', 'Text me when something big happens.'], 'email_marketing' => ['Offers and rates', 'Email me about new rates and offers.']] as $key => [$title, $desc])
        <div class="srow">
            <div><h3>{{ $title }}</h3><p>{{ $desc }}</p></div>
            <label class="switch"><input type="checkbox" name="{{ $key }}" value="1" @checked($prefs[$key]) aria-label="{{ $title }}"><i></i></label>
        </div>
    @endforeach
    <div class="srow" style="background:#f6f8fe"><p>Security alerts, such as a new device sign-in, are always sent.</p><button class="btn-s sm" type="submit">Save preferences</button></div>
</form>

<dialog id="dlg-contact">
    <div class="dh"><h3>Edit contact details</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
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
