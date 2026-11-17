@extends('layouts.portal')
@section('title', 'Profile')
@section('content')
<h1>Profile</h1>
<p class="psub">Member #{{ $user->member_number }} &middot; Member since {{ $user->profile?->membership_opened_at?->format('F Y') ?? $user->created_at->format('F Y') }}</p>

<div class="two">
<div class="panel-card">
    <h2>Contact details</h2>
    <dl class="kv" style="margin-bottom:1rem"><dt>Name</dt><dd>{{ $user->name }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd></dl>
    <p class="fine">To change your name or email, call Customer Care so we can verify it is you.</p>
    <form class="form" method="post" action="{{ route('user-profile-information.update') }}">
        @csrf @method('PUT')
        <label>Mobile phone<input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" required>@error('phone', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Street address<input name="address_line1" value="{{ old('address_line1', $user->address_line1) }}" required>@error('address_line1', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        <div class="row" style="grid-template-columns:2fr 1fr 1fr">
            <label>City<input name="city" value="{{ old('city', $user->city) }}" required>@error('city', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
            <label>State<input name="state" value="{{ old('state', $user->state) }}" maxlength="2" required style="text-transform:uppercase">@error('state', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
            <label>ZIP<input name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" required>@error('postal_code', 'updateProfileInformation')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <button class="btn" type="submit">Save changes</button>
    </form>
</div>

<div class="panel-card">
    <h2>Notifications</h2>
    <form class="form" method="post" action="{{ route('profile.notifications') }}">
        @csrf @method('PUT')
        @foreach (['email_transactions' => 'Email me about deposits, transfers and payments', 'sms_alerts' => 'Text me about large transactions and sign-ins', 'email_marketing' => 'Email me about offers and rates'] as $key => $label)
            <label style="grid-template-columns:auto 1fr;align-items:center;gap:.6rem;font-weight:400"><input type="checkbox" name="{{ $key }}" value="1" style="min-height:0" @checked($prefs[$key])> {{ $label }}</label>
        @endforeach
        <p class="fine" style="margin:0">Security alerts, such as a new device sign-in, are always sent.</p>
        <button class="btn" type="submit">Save preferences</button>
    </form>
</div>
</div>
@endsection
