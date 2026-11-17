@extends('layouts.portal')
@section('title', 'Deposit a check')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('deposits.index') }}">&larr; Deposit</a></p>
<h1>Deposit a check</h1>
<p class="psub">Up to {{ $limit->format() }} per check. A person reviews every check before the money is added.</p>
@endsection
@section('content')
@unless ($verified)
    <div class="notice-bar" role="status"><p>You need to verify your identity before you can deposit a check.</p><p><a class="btn" href="{{ route('kyc.show') }}">Verify my identity</a></p></div>
@else
<div class="panel-card">
    <form class="form" method="post" action="{{ route('deposits.check.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Deposit into
            <select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select>
            @error('account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Amount of the check<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required autocomplete="off">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Check number (optional)<input name="check_number" value="{{ old('check_number') }}" inputmode="numeric" maxlength="12">@error('check_number')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Front of the check<input type="file" name="front" accept="image/png,image/jpeg,image/webp" required>@error('front')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Back of the check<input type="file" name="back" accept="image/png,image/jpeg,image/webp" required>@error('back')<span class="err">{{ $message }}</span>@enderror</label>
        @include('portal._staff-code')
        <button class="btn" type="submit">Submit deposit</button>
    </form>
    <p class="fine" style="margin-top:1rem">Sign the back and write "For mobile deposit only at {{ brand('short_name') }}" under your signature. Photos must show all four corners in good light. Keep the paper check for 14 days, then shred it.</p>
</div>
@endunless
@endsection
