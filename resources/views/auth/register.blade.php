@extends('layouts.auth')
@section('title', 'Become a member | Golden West Federal Credit Union')
@section('box-class', 'wide')
@section('heading', 'Become a member')
@section('lede', 'Joining is free. You will have a savings and a checking account as soon as you finish, with no opening deposit.')
@section('form')
<form class="form" method="post" action="{{ route('register.store') }}" style="max-width:none">
    @csrf
    <h3 style="margin:.5rem 0 0">About you</h3>
    <div class="row">
        <label>First name<input name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name">@error('first_name')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Last name<input name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name">@error('last_name')<span class="err">{{ $message }}</span>@enderror</label>
    </div>
    <div class="row">
        <label>Date of birth<input type="date" name="dob" value="{{ old('dob') }}" required autocomplete="bday">@error('dob')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Last 4 of your Social Security number<input name="ssn_last4" value="{{ old('ssn_last4') }}" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="off">@error('ssn_last4')<span class="err">{{ $message }}</span>@enderror</label>
    </div>
    <div class="row">
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Mobile phone<input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">@error('phone')<span class="err">{{ $message }}</span>@enderror</label>
    </div>
    <h3 style="margin:.5rem 0 0">Home address</h3>
    <label>Street address<input name="address_line1" value="{{ old('address_line1') }}" required autocomplete="address-line1">@error('address_line1')<span class="err">{{ $message }}</span>@enderror</label>
    <div class="row" style="grid-template-columns:2fr 1fr 1fr">
        <label>City<input name="city" value="{{ old('city') }}" required autocomplete="address-level2">@error('city')<span class="err">{{ $message }}</span>@enderror</label>
        <label>State<input name="state" value="{{ old('state') }}" maxlength="2" required autocomplete="address-level1" style="text-transform:uppercase">@error('state')<span class="err">{{ $message }}</span>@enderror</label>
        <label>ZIP code<input name="postal_code" value="{{ old('postal_code') }}" required autocomplete="postal-code">@error('postal_code')<span class="err">{{ $message }}</span>@enderror</label>
    </div>
    <h3 style="margin:.5rem 0 0">How you qualify</h3>
    <label>I am eligible because
        <select name="field_of_membership" id="fom" required>
            @foreach ($fields as $value => $label)<option value="{{ $value }}" @selected(old('field_of_membership') === $value)>{{ $label }}</option>@endforeach
        </select>
        @error('field_of_membership')<span class="err">{{ $message }}</span>@enderror
    </label>
    <label id="state-row">State of your connection
        <select name="eligibility_state">
            <option value="">Choose a state</option>
            @foreach (config('bank.region_states') as $st)<option @selected(old('eligibility_state') === $st)>{{ $st }}</option>@endforeach
        </select>
        @error('eligibility_state')<span class="err">{{ $message }}</span>@enderror
    </label>
    <label id="family-row" hidden>Your family member's member number<input name="referred_by_member_number" value="{{ old('referred_by_member_number') }}" inputmode="numeric" maxlength="7">@error('referred_by_member_number')<span class="err">{{ $message }}</span>@enderror</label>
    <h3 style="margin:.5rem 0 0">Create your login</h3>
    <div class="row">
        <label>Password<input type="password" name="password" required autocomplete="new-password" minlength="12">@error('password')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    </div>
    <p class="fine" style="margin:0">Use at least 12 characters with upper and lower case letters and a number.</p>
    <label style="grid-template-columns:auto 1fr;align-items:start;gap:.6rem;font-weight:400"><input type="checkbox" name="terms" value="1" style="min-height:0;margin-top:.3rem" @checked(old('terms')) required> <span>I agree to the <a href="{{ url('/terms') }}" style="color:var(--blue)">Terms of Use</a> and have read the <a href="{{ url('/privacy') }}" style="color:var(--blue)">Privacy notice</a>.</span></label>
    @error('terms')<span class="err">{{ $message }}</span>@enderror
    <button class="btn" type="submit">Open my accounts</button>
</form>
<p class="fine" style="margin-top:1.25rem">Already a member? <a href="{{ route('login') }}" style="color:var(--blue)">Log in</a></p>
@push('scripts')
<script>
(function () {
    var sel = document.getElementById('fom'), st = document.getElementById('state-row'), fam = document.getElementById('family-row');
    function sync() { var f = sel.value === 'family_member'; st.hidden = f; fam.hidden = !f; }
    sel.addEventListener('change', sync); sync();
})();
</script>
@endpush
@endsection
