@extends('layouts.portal')
@section('title', 'Business')
@section('band')
<h1>Business</h1>
<p class="psub">Business accounts sit under your membership. Same member number, same login.</p>
@endsection
@section('content')
@if ($profile)
    @if ($profile->isPending())
        <div class="notice-bar" role="status"><p><strong>We are reviewing your business document.</strong> This usually takes one business day. Your business accounts open as soon as it is approved, and we will email you. <a class="plink" href="{{ route('kyc.show') }}">See your documents</a></p></div>
    @endif
    <div class="panel-card">
        <h3 style="margin-top:0">{{ $profile->legal_name }}</h3>
        <dl class="kvlist" style="padding:0"><dt>Type</dt><dd>{{ $profile->entityLabel() }}</dd><dt>EIN</dt><dd>{{ $profile->maskedEin() }}</dd><dt>What it does</dt><dd>{{ $profile->industry }}</dd><dt>In business since</dt><dd>{{ $profile->started_year }}</dd><dt>Address</dt><dd>{{ $profile->address_line1 }}, {{ $profile->city }}, {{ $profile->state }} {{ $profile->postal_code }}</dd></dl>
    </div>
    @if ($accounts->count())
        <div class="table-title"><h2>Business accounts</h2></div>
        <div class="panel-card flush"><table class="dtable"><tbody>
        @foreach ($accounts as $a)
            <tr><td><a class="main" href="{{ route('accounts.show', $a) }}">{{ $a->nickname }}</a><small>{{ $a->kindLabel() }} {{ $a->masked() }}</small></td><td>{{ $a->balance->format() }}</td></tr>
        @endforeach
        </tbody></table></div>
    @endif
    @if ($missing)
        <div class="panel-card" style="margin-top:1.25rem">
            <h3 style="margin-top:0">Open another business account</h3>
            <form class="form" method="post" action="{{ route('business.open') }}">@csrf
                <label>Account<select name="product">@foreach ($missing as $code)<option value="{{ $code }}">{{ $code === 'BUSINESS-CHECKING' ? 'Business Checking' : 'Business Savings' }}</option>@endforeach</select></label>
                <button class="btn" type="submit">Open account</button>
            </form>
        </div>
    @endif
@elseif (! $verified)
    <div class="notice-bar" role="status"><p><strong>Verify your identity first.</strong> We add a business only for verified members. <a class="plink" href="{{ route('kyc.show') }}">Verify your identity</a></p></div>
@else
    <div class="panel-card">
        <h3 style="margin-top:0">Add a business</h3>
        <p class="fine">Tell us about the business and upload one document that shows its name, such as articles of organization or incorporation, a business license, or an IRS letter with the EIN. We review it, usually within one business day, and then open your business accounts.</p>
        <form class="form" method="post" action="{{ route('business.store') }}" enctype="multipart/form-data">@csrf
            <label>Legal name of the business<input name="business_name" value="{{ old('business_name') }}" maxlength="120" required>@error('business_name')<span class="err">{{ $message }}</span>@enderror</label>
            <div class="row">
                <label>Type of business<select name="business_type" required><option value="">Choose one</option>@foreach (\App\Models\BusinessProfile::ENTITY_TYPES as $v => $l)<option value="{{ $v }}" @selected(old('business_type') === $v)>{{ $l }}</option>@endforeach</select>@error('business_type')<span class="err">{{ $message }}</span>@enderror</label>
                <label>EIN<input name="business_ein" value="{{ old('business_ein') }}" inputmode="numeric" maxlength="10" placeholder="12-3456789" required autocomplete="off">@error('business_ein')<span class="err">{{ $message }}</span>@enderror</label>
            </div>
            <div class="row">
                <label>What the business does<input name="business_industry" value="{{ old('business_industry') }}" maxlength="80" required>@error('business_industry')<span class="err">{{ $message }}</span>@enderror</label>
                <label>Year started<input name="business_started_year" value="{{ old('business_started_year') }}" inputmode="numeric" maxlength="4" required>@error('business_started_year')<span class="err">{{ $message }}</span>@enderror</label>
            </div>
            <label>Business street address<input name="business_address_line1" value="{{ old('business_address_line1') }}" maxlength="160" required>@error('business_address_line1')<span class="err">{{ $message }}</span>@enderror</label>
            <div class="row row3">
                <label>City<input name="business_city" value="{{ old('business_city') }}" maxlength="80" required>@error('business_city')<span class="err">{{ $message }}</span>@enderror</label>
                <label>State<input name="business_state" value="{{ old('business_state') }}" maxlength="2" minlength="2" required style="text-transform:uppercase">@error('business_state')<span class="err">{{ $message }}</span>@enderror</label>
                <label>ZIP code<input name="business_postal_code" value="{{ old('business_postal_code') }}" pattern="\d{5}(-\d{4})?" inputmode="numeric" required>@error('business_postal_code')<span class="err">{{ $message }}</span>@enderror</label>
            </div>
            <div class="choices">
                @foreach (\App\Models\BusinessProfile::ACCOUNT_CHOICES as $value => $label)
                    <label class="choice"><input type="radio" name="business_accounts" value="{{ $value }}" @checked(old('business_accounts', 'both') === $value)><span><strong>{{ $label }}</strong><small>{{ ['both' => 'Business Checking for day-to-day money, plus Business Savings for reserves and taxes.', 'checking' => 'Business Checking with a business debit card and a $50,000 daily limit.', 'savings' => 'Business Savings that earns dividends.'][$value] }}</small></span></label>
                @endforeach
            </div>
            @error('business_accounts')<span class="err">{{ $message }}</span>@enderror
            <label>Business document
                <small class="hint">PNG, JPG, WebP or PDF, up to 8 MB. Stored privately.</small>
                <input type="file" name="business_document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
                @error('business_document')<span class="err">{{ $message }}</span>@enderror
            </label>
            <button class="btn" type="submit">Send for review</button>
        </form>
    </div>
@endif
@endsection
