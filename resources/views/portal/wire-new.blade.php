@extends('layouts.portal')
@section('title', 'Send a wire')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('wires.index') }}">&larr; Wire transfers</a></p>
<h1>Send a wire</h1>
<p class="psub">Fee {{ $fee->format() }}@if ($international) in the United States, {{ $intlFee->format() }} to another country @endif. Requested now, it is sent {{ $processOn->isToday() ? 'today' : 'on '.$processOn->format('l, F j') }} once approved. The cutoff for same-day wires is {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $cutoff)->format('g:i a') }} Pacific time.</p>
@endsection
@section('content')
@if (! $verified)
    <div class="notice-bar" role="status"><p>You need to verify your identity before you can send a wire.</p><p><a class="btn" href="{{ route('kyc.show') }}">Verify my identity</a></p></div>
@else
<form class="wireform" method="post" action="{{ route('wires.store') }}" autocomplete="off">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="panel-card form">
        <h3>1. Where the money comes from</h3>
        <label>From account
            <select name="account_id" required>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->nickname }} {{ $a->masked() }} - available {{ $a->available_balance->format() }}</option>@endforeach</select>
            @error('account_id')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Wire amount<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required>@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <p class="fine" style="margin:0">The <span class="wirefee">{{ $fee->format() }}</span> fee is taken from the same account on top of the amount.</p>
    </div>

    <div class="panel-card form" id="recipient-card">
        <h3>2. Who receives it</h3>
        @if ($international)
        <fieldset id="kind-pick" style="border:0;padding:0;margin:0;display:flex;gap:1.5rem;flex-wrap:wrap">
            <legend style="padding:0;font-weight:600;margin-bottom:.4rem">Where is it going?</legend>
            <label style="display:flex;gap:.5rem;align-items:center"><input type="radio" name="kind" value="domestic" @checked(old('kind', 'domestic') === 'domestic')> A bank in the United States</label>
            <label style="display:flex;gap:.5rem;align-items:center"><input type="radio" name="kind" value="international" @checked(old('kind') === 'international')> A bank in another country</label>
        </fieldset>
        @endif
        @if ($recipients->count())
        <label>Send to
            <select name="recipient_id" id="recipient-pick">
                <option value="new" @selected(old('recipient_id', 'new') === 'new')>A new recipient</option>
                @foreach ($recipients as $r)<option value="{{ $r->id }}" @selected((string) old('recipient_id') === (string) $r->id)>{{ $r->label() }}</option>@endforeach
            </select>
        </label>
        @endif
        <div id="new-recipient" class="newrecipient">
            <label>Recipient's full name<input name="recipient_name" value="{{ old('recipient_name') }}" maxlength="120">@error('recipient_name')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Recipient's address<input name="recipient_address" value="{{ old('recipient_address') }}" maxlength="200" placeholder="Street, city, state, ZIP">@error('recipient_address')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Recipient's bank<input name="bank_name" value="{{ old('bank_name') }}" maxlength="120">@error('bank_name')<span class="err">{{ $message }}</span>@enderror</label>
            <div id="dom-fields" style="display:contents">
                <label>Bank routing number<input name="routing_number" value="{{ old('routing_number') }}" inputmode="numeric" maxlength="9">@error('routing_number')<span class="err">{{ $message }}</span>@enderror</label>
            </div>
            @if ($international)
            <div id="intl-fields" hidden style="display:contents">
                <label>Country of the recipient's bank
                    <select name="country"><option value="">Choose a country</option>@foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>@endforeach</select>
                    @error('country')<span class="err">{{ $message }}</span>@enderror</label>
                <label>Bank SWIFT/BIC code<input name="swift" value="{{ old('swift') }}" maxlength="11" placeholder="For example DEUTDEFF" style="text-transform:uppercase" spellcheck="false">@error('swift')<span class="err">{{ $message }}</span>@enderror</label>
            </div>
            @endif
            <label><span id="acct-label">Account number</span><input name="account_number" maxlength="42" autocomplete="off" spellcheck="false">@error('account_number')<span class="err">{{ $message }}</span>@enderror</label>
            <label><span id="acct-label2">Account number again</span><input name="account_number_confirmation" maxlength="42" autocomplete="off" spellcheck="false"></label>
            @if ($international)<p class="fine" id="intl-note" hidden style="margin:0">Wires abroad are sent in US dollars. The recipient's bank may convert them and charge its own fees. They usually arrive one to three business days after we send them. In {{ implode(', ', array_slice(array_values(array_intersect_key($countries, array_flip($ibanCountries))), 0, 6)) }} and other countries that use IBANs, enter the recipient's IBAN as the account number.</p>@endif
            <label class="chk" style="display:flex;gap:.6rem;align-items:center"><input type="checkbox" name="save_recipient" value="1" @checked(old('save_recipient'))><span>Save this recipient for my next wire</span></label>
        </div>
    </div>

    <div class="panel-card form">
        <h3>3. What it is for</h3>
        <label>Purpose of the wire<input name="purpose" value="{{ old('purpose') }}" maxlength="140" placeholder="For example: down payment on a home" required>@error('purpose')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Note to the recipient (optional)<input name="message" value="{{ old('message') }}" maxlength="140">@error('message')<span class="err">{{ $message }}</span>@enderror</label>
    </div>

    <div class="panel-card form">
        <h3>4. Review and send</h3>
        <label class="chk" style="display:flex;gap:.6rem;align-items:flex-start"><input type="checkbox" name="understand" value="1" @checked(old('understand')) style="margin-top:.3rem"><span>I checked the details above. I understand a wire cannot be taken back once it is sent, and that the <span class="wirefee">{{ $fee->format() }}</span> fee is charged with it.</span></label>
        @error('understand')<span class="err">{{ $message }}</span>@enderror
        @include('portal._staff-code')
        <p class="fine" style="margin:0">A {{ brand('short_name') }} team member reviews every wire before it is sent. For your protection we may call you on the number we have on file. We will never call to ask you to send a wire. If someone asks you to, hang up and call Customer Care at {{ brand('support_phone') }}.</p>
        <div><button class="btn" type="submit">Request wire</button></div>
    </div>
</form>
<script>
(function () {
    var pick = document.getElementById('recipient-pick'), box = document.getElementById('new-recipient');
    var fees = {domestic: @json($fee->format()), international: @json($intlFee->format())};
    var dom = document.getElementById('dom-fields'), intl = document.getElementById('intl-fields'), note = document.getElementById('intl-note');
    function kind() { var r = document.querySelector('input[name=kind]:checked'); return r ? r.value : 'domestic'; }
    function toggle(el, on) { if (!el) return; el.hidden = !on; el.querySelectorAll('input,select').forEach(function (i) { i.disabled = !on; }); }
    function sync() {
        var k = kind(), saved = pick && pick.value !== 'new';
        if (box) box.hidden = saved;
        toggle(dom, k === 'domestic'); toggle(intl, k === 'international'); if (note) note.hidden = k !== 'international';
        document.getElementById('acct-label').textContent = k === 'international' ? 'IBAN or account number' : 'Account number';
        document.getElementById('acct-label2').textContent = k === 'international' ? 'IBAN or account number again' : 'Account number again';
        document.querySelectorAll('.wirefee').forEach(function (e) { e.textContent = fees[k]; });
    }
    if (pick) pick.addEventListener('change', sync);
    document.querySelectorAll('input[name=kind]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
@endif
@endsection
