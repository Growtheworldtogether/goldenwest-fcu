@extends('layouts.auth')
@section('title', 'Become a member | '.brand('name'))
@section('box-class', 'wide')
@section('art', 'branch.jpg')
@section('art-title', 'Join in about five minutes')
@section('art-text', 'Free savings and checking, no opening deposit, and a team that knows your name.')
@section('heading', 'Become a member')
@section('lede', 'Joining is free. Your accounts open as soon as you finish, with no opening deposit.')
@section('form')
<form class="form wizard" id="join" method="post" action="{{ route('register.store') }}" enctype="multipart/form-data" style="max-width:none" novalidate>
    @csrf
    <ol class="wiz-nav" id="wiz-nav" hidden aria-label="Progress"></ol>
    <p class="wiz-count" id="wiz-count" hidden aria-live="polite"></p>

    <fieldset class="step" data-step="1" data-title="Eligibility">
        <legend>How you qualify</legend>
        <p class="fine" style="margin:0">Membership is open to anyone with a connection to {{ \App\Support\Region::sentence('or') }}, and to family of our members.</p>
        <label>I am eligible because
            <select name="field_of_membership" id="fom" required>
                @foreach ($fields as $value => $label)<option value="{{ $value }}" @selected(old('field_of_membership') === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('field_of_membership')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label id="state-row">State of your connection
            <select name="eligibility_state" id="elig-state">
                <option value="">Choose a state</option>
                @foreach (\App\Support\Region::codes() as $st)<option @selected(old('eligibility_state') === $st)>{{ $st }}</option>@endforeach
            </select>
            @error('eligibility_state')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label id="family-row" hidden>Your family member's member number<input name="referred_by_member_number" id="family-no" value="{{ old('referred_by_member_number') }}" inputmode="numeric" maxlength="7" pattern="\d{7}" autocomplete="off">@error('referred_by_member_number')<span class="err">{{ $message }}</span>@enderror</label>
    </fieldset>

    <fieldset class="step" data-step="2" data-title="About you">
        <legend>About you</legend>
        <p class="fine" style="margin:0">Use your name as it appears on your government ID.</p>
        <div class="row">
            <label>First name<input name="first_name" value="{{ old('first_name') }}" required maxlength="80" autocomplete="given-name">@error('first_name')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Last name<input name="last_name" value="{{ old('last_name') }}" required maxlength="80" autocomplete="family-name">@error('last_name')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <div class="row">
            <label>Date of birth<input type="date" name="dob" value="{{ old('dob') }}" required max="{{ now()->subYears(18)->toDateString() }}" autocomplete="bday">@error('dob')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Last 4 of your Social Security number<input name="ssn_last4" value="{{ old('ssn_last4') }}" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="off">@error('ssn_last4')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
    </fieldset>

    <fieldset class="step" data-step="3" data-title="Contact">
        <legend>How we reach you</legend>
        <div class="row">
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="160" autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Mobile phone<input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel">@error('phone')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <label>Street address<input name="address_line1" value="{{ old('address_line1') }}" required maxlength="160" autocomplete="address-line1">@error('address_line1')<span class="err">{{ $message }}</span>@enderror</label>
        <div class="row row3">
            <label>City<input name="city" value="{{ old('city') }}" required maxlength="80" autocomplete="address-level2">@error('city')<span class="err">{{ $message }}</span>@enderror</label>
            <label>State<input name="state" value="{{ old('state') }}" maxlength="2" minlength="2" required autocomplete="address-level1" style="text-transform:uppercase">@error('state')<span class="err">{{ $message }}</span>@enderror</label>
            <label>ZIP code<input name="postal_code" value="{{ old('postal_code') }}" required pattern="\d{5}(-\d{4})?" inputmode="numeric" autocomplete="postal-code">@error('postal_code')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
    </fieldset>

    <fieldset class="step" data-step="4" data-title="Accounts">
        <legend>Choose your accounts</legend>
        <p class="fine" style="margin:0">Both are free, with no minimum balance. You can open more accounts any time after you join.</p>
        <div class="choices">
            @foreach ($choices as $value => $label)
                <label class="choice"><input type="radio" name="account_choice" value="{{ $value }}" @checked(old('account_choice', 'both') === $value)>
                    <span><strong>{{ $label }}</strong>
                    <small>{{ ['both' => 'Everyday Checking for debit card and bills, plus Golden Savings. Savings backs up checking against overdrafts.', 'savings' => 'Golden Savings, our share savings account that earns dividends.', 'checking' => 'Everyday Checking with a debit card, bill pay and checks.'][$value] }}</small></span>
                </label>
            @endforeach
        </div>
        @error('account_choice')<span class="err">{{ $message }}</span>@enderror
    </fieldset>

    <fieldset class="step" data-step="5" data-title="Documents">
        <legend>Confirm who you are</legend>
        <p class="fine" style="margin:0">Federal rules require us to verify every new member. Upload a clear photo, scan or PDF of each, up to 8 MB. They are stored privately and only our identity team can open them.</p>
        <label>Government photo ID
            <small class="hint">A driver license, state ID or passport. The photo and name must be easy to read.</small>
            <input type="file" name="id_document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
            @error('id_document')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Proof of address
            <small class="hint">A recent utility bill, bank statement or lease that shows your name and home address.</small>
            <input type="file" name="address_document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
            @error('address_document')<span class="err">{{ $message }}</span>@enderror
        </label>
    </fieldset>

    <fieldset class="step" data-step="6" data-title="Password">
        <legend>Create your login</legend>
        <div class="row">
            <label>Password<input type="password" name="password" id="pw" required autocomplete="new-password" minlength="12">@error('password')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Confirm password<input type="password" name="password_confirmation" id="pw2" required autocomplete="new-password"></label>
        </div>
        <ul class="pwrules" id="pwrules" aria-label="Password requirements">
            <li data-rule="len">At least 12 characters</li>
            <li data-rule="lower">A lowercase letter</li>
            <li data-rule="upper">An uppercase letter</li>
            <li data-rule="num">A number</li>
            <li data-rule="match">Both entries match</li>
        </ul>
        <label class="check"><input type="checkbox" id="showpw"> <span>Show password</span></label>
    </fieldset>

    <fieldset class="step" data-step="7" data-title="Review">
        <legend>Review and finish</legend>
        <dl class="review" id="review"></dl>
        <label class="check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required> <span>I agree to the <a href="{{ url('/terms') }}" target="_blank" rel="noopener" style="color:var(--blue)">Terms of Use</a> and have read the <a href="{{ url('/privacy') }}" target="_blank" rel="noopener" style="color:var(--blue)">Privacy notice</a>.</span></label>
        @error('terms')<span class="err">{{ $message }}</span>@enderror
    </fieldset>

    <div class="wiz-actions">
        <button type="button" class="btn ghost" id="wiz-back" hidden>Back</button>
        <button type="button" class="btn" id="wiz-next" hidden>Continue</button>
        <button class="btn" type="submit" id="wiz-submit">Open my accounts</button>
    </div>
</form>
<p class="fine" style="margin-top:1.25rem">Already a member? <a href="{{ route('login') }}" style="color:var(--blue)">Log in</a></p>
@push('scripts')
<script>
(function () {
    var form = document.getElementById('join');
    var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
    var nav = document.getElementById('wiz-nav'), count = document.getElementById('wiz-count');
    var back = document.getElementById('wiz-back'), next = document.getElementById('wiz-next'), submit = document.getElementById('wiz-submit');
    var sel = document.getElementById('fom'), stRow = document.getElementById('state-row'), famRow = document.getElementById('family-row');
    var elig = document.getElementById('elig-state'), famNo = document.getElementById('family-no');
    var pw = document.getElementById('pw'), pw2 = document.getElementById('pw2');
    var current = 0, reached = 0;

    function syncEligibility() {
        var fam = sel.value === 'family_member';
        stRow.hidden = fam; famRow.hidden = !fam;
        elig.required = !fam; famNo.required = fam;
    }
    sel.addEventListener('change', syncEligibility); syncEligibility();

    // Password rules, live.
    var rules = { len: function (v) { return v.length >= 12; }, lower: function (v) { return /[a-z]/.test(v); }, upper: function (v) { return /[A-Z]/.test(v); }, num: function (v) { return /\d/.test(v); }, match: function (v) { return v !== '' && v === pw2.value; } };
    function syncRules() {
        document.querySelectorAll('#pwrules li').forEach(function (li) { li.classList.toggle('ok', rules[li.dataset.rule](pw.value)); });
    }
    pw.addEventListener('input', syncRules); pw2.addEventListener('input', syncRules); syncRules();
    document.getElementById('showpw').addEventListener('change', function () { pw.type = pw2.type = this.checked ? 'text' : 'password'; });

    form.querySelector('[name=state]').addEventListener('input', function () { this.value = this.value.toUpperCase().replace(/[^A-Z]/g, ''); });
    form.querySelector('[name=ssn_last4]').addEventListener('input', function () { this.value = this.value.replace(/\D/g, ''); });

    // From here on the form is a wizard. Without script it stays one long page and still works.
    form.classList.add('is-wizard');
    nav.hidden = false; count.hidden = false; back.hidden = false; next.hidden = false;
    steps.forEach(function (s, i) {
        var li = document.createElement('li');
        var b = document.createElement('button'); b.type = 'button';
        b.innerHTML = '<span class="dot">' + (i + 1) + '</span><span class="lbl"></span>';
        b.querySelector('.lbl').textContent = s.dataset.title;
        b.addEventListener('click', function () { if (i <= reached) show(i, true); });
        li.appendChild(b); nav.appendChild(li);
    });

    function clearErr(field) {
        var e = field.parentNode.querySelector('.js-err'); if (e) e.remove();
        field.removeAttribute('aria-invalid');
    }
    function setErr(field, msg) {
        clearErr(field);
        var s = document.createElement('span'); s.className = 'err js-err'; s.setAttribute('role', 'alert'); s.textContent = msg;
        field.parentNode.appendChild(s); field.setAttribute('aria-invalid', 'true');
    }
    function message(f) {
        var v = f.validity, n = f.name;
        if (v.valueMissing) return f.type === 'checkbox' ? 'Please agree to continue.' : f.type === 'file' ? 'Choose a file to upload.' : 'This is required.';
        if (f.type === 'file' && f.files[0] && f.files[0].size > 8 * 1024 * 1024) return 'The file must be 8 MB or smaller.';
        if (n === 'dob' && (v.rangeOverflow || v.badInput)) return 'You must be at least 18 to open an account.';
        if (n === 'ssn_last4' && !v.valid) return 'Enter exactly four digits.';
        if (n === 'postal_code' && !v.valid) return 'Enter a five-digit ZIP code.';
        if (n === 'state' && !v.valid) return 'Use the two-letter state code, such as CA.';
        if (n === 'email' && !v.valid) return 'Enter a valid email address.';
        if (n === 'referred_by_member_number' && !v.valid) return 'A member number has seven digits.';
        if (n === 'password' && v.tooShort) return 'Use at least 12 characters.';
        return f.validationMessage;
    }
    function validate(step) {
        var ok = true, first = null;
        step.querySelectorAll('input:not([type=radio]), select').forEach(function (f) {
            if (f.closest('[hidden]') || f.disabled) return;
            clearErr(f);
            var bad = !f.checkValidity() ? message(f) : null;
            if (!bad && f.name === 'password' && !(rules.lower(f.value) && rules.upper(f.value) && rules.num(f.value))) bad = 'Include an uppercase letter, a lowercase letter and a number.';
            if (!bad && f.name === 'password_confirmation' && f.value !== pw.value) bad = 'The two passwords do not match.';
            if (!bad && f.type === 'file' && f.files[0] && f.files[0].size > 8 * 1024 * 1024) bad = 'The file must be 8 MB or smaller.';
            if (bad) { setErr(f, bad); ok = ok && false; first = first || f; }
        });
        if (first) first.focus();
        return ok;
    }
    function choiceName() { var r = form.querySelector('[name=account_choice]:checked'); return r ? r.parentNode.querySelector('strong').textContent : 'Savings and checking'; }
    function fileName(n) { var f = form.elements[n]; return f && f.files[0] ? f.files[0].name : 'Not chosen'; }
    function buildReview() {
        var g = function (n) { var f = form.elements[n]; return f ? f.value.trim() : ''; };
        var how = sel.options[sel.selectedIndex].text + (sel.value === 'family_member' ? ' (member ' + g('referred_by_member_number') + ')' : ', ' + g('eligibility_state'));
        var rows = [
            ['Eligibility', how, 0], ['Name', g('first_name') + ' ' + g('last_name'), 1], ['Date of birth', g('dob'), 1], ['Social Security number', 'Ending in ••••', 1],
            ['Email', g('email'), 2], ['Mobile phone', g('phone'), 2], ['Address', g('address_line1') + ', ' + g('city') + ', ' + g('state') + ' ' + g('postal_code'), 2], ['Accounts', choiceName(), 3],
            ['Government ID', fileName('id_document'), 4], ['Proof of address', fileName('address_document'), 4], ['Password', '•'.repeat(12), 5]
        ];
        var dl = document.getElementById('review'); dl.innerHTML = '';
        rows.forEach(function (r) {
            var dt = document.createElement('dt'), dd = document.createElement('dd'), a = document.createElement('button');
            dt.textContent = r[0]; dd.textContent = r[1] + ' '; a.type = 'button'; a.className = 'linkbtn'; a.textContent = 'Change';
            a.addEventListener('click', function () { show(r[2], true); }); dd.appendChild(a); dl.appendChild(dt); dl.appendChild(dd);
        });
    }
    function show(i, focus) {
        current = i; reached = Math.max(reached, i);
        steps.forEach(function (s, k) { s.hidden = k !== i; s.classList.remove('enter'); });
        steps[i].classList.add('enter');
        var last = i === steps.length - 1;
        back.hidden = i === 0; next.hidden = last; submit.hidden = !last;
        count.textContent = 'Step ' + (i + 1) + ' of ' + steps.length + ': ' + steps[i].dataset.title;
        nav.querySelectorAll('li').forEach(function (li, k) { li.classList.toggle('current', k === i); li.classList.toggle('done', k < i || (k <= reached && k !== i)); li.querySelector('button').disabled = k > reached; if (k === i) li.querySelector('button').setAttribute('aria-current', 'step'); else li.querySelector('button').removeAttribute('aria-current'); });
        if (last) buildReview();
        if (focus) { var f = steps[i].querySelector('input:not([type=checkbox]):not([type=radio]), select'); var vis = f && !f.closest('[hidden]') ? f : steps[i].querySelector('legend'); if (vis) { if (!vis.hasAttribute('tabindex') && vis.tagName === 'LEGEND') vis.setAttribute('tabindex', '-1'); vis.focus({ preventScroll: true }); } form.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
    }
    function go() { if (validate(steps[current])) show(current + 1, true); }
    next.addEventListener('click', go);
    back.addEventListener('click', function () { show(current - 1, true); });
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.type !== 'checkbox' && current < steps.length - 1) { e.preventDefault(); go(); }
    });
    form.addEventListener('submit', function (e) {
        for (var i = 0; i < steps.length; i++) { if (!validate(steps[i])) { e.preventDefault(); show(i, false); validate(steps[i]); return; } }
    });
    form.addEventListener('input', function (e) { if (e.target.matches('input, select')) clearErr(e.target); });

    // After the server sends the form back with errors, open the first step that has one.
    var firstBad = steps.findIndex(function (s) { return s.querySelector('.err'); });
    if (firstBad >= 0) { reached = steps.length - 1; show(firstBad, false); } else { show(0, false); }
})();
</script>
@endpush
@endsection
