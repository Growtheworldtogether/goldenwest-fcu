@extends('layouts.portal')
@section('title', 'Apply for a loan')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('borrow.index') }}">&larr; Loans</a></p>
<h1>Apply for a loan</h1>
<p class="psub">Most decisions take one business day. Applying does not affect anything until you accept.</p>
@endsection
@section('content')
@php($sel = old('product', $selected) ?: $products->first()->code)
<div class="panel-card">
<form class="form" method="post" action="{{ route('borrow.store') }}" id="loanform">@csrf
    <label>Loan type<select name="product" id="product">@foreach ($products as $p)<option value="{{ $p->code }}" data-type="{{ $p->type }}" data-seg="{{ $p->segment }}" data-rev="{{ $p->revolving ? 1 : 0 }}" data-terms="{{ implode(',', $p->terms) }}" data-min="{{ $p->apr_min_bps }}" data-max="{{ $p->apr_max_bps }}" @selected($sel === $p->code)>{{ $p->name }} ({{ $p->aprRange() }})</option>@endforeach</select>@error('product')<span class="err">{{ $message }}</span>@enderror</label>
    <div class="two">
        <label><span id="amtlabel">Amount ($)</span><input name="amount" inputmode="decimal" required value="{{ old('amount') }}" id="amount">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Term<select name="term_months" id="term">@foreach ($products->flatMap->terms->unique()->sort() as $t)<option value="{{ $t }}" @selected((int) old('term_months') === $t)>{{ $t }} months</option>@endforeach</select>@error('term_months')<span class="err">{{ $message }}</span>@enderror</label>
    </div>
    <p class="fine" id="est" style="margin:-.25rem 0 0"></p>
    @if ($business)<p class="fine" id="bizinfo" style="margin:0" hidden>Applying for {{ $business->legal_name }}, {{ $business->entityLabel() }}, in business since {{ $business->started_year }}.</p>@endif
    <label>What is the loan for?<input name="purpose" maxlength="120" required value="{{ old('purpose') }}">@error('purpose')<span class="err">{{ $message }}</span>@enderror</label>
    <div class="two">
        <label><span id="inclabel">Annual income before tax ($)</span><input name="annual_income" inputmode="decimal" required value="{{ old('annual_income') }}">@error('annual_income')<span class="err">{{ $message }}</span>@enderror</label>
        <label id="emprow">Employment<select name="employment">@foreach (['employed' => 'Employed', 'self_employed' => 'Self-employed', 'retired' => 'Retired', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected(old('employment') === $k)>{{ $l }}</option>@endforeach</select></label>
    </div>
    <div id="equip" style="display:none">
        <div class="two">
            <label>Equipment you are buying<input name="collateral" maxlength="120" value="{{ old('collateral') }}" placeholder="For example, 2024 Kubota mini excavator">@error('collateral')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Equipment cost ($)<input name="collateral_value" inputmode="decimal" value="{{ old('collateral_value') }}"></label>
        </div>
    </div>
    <div id="vehicle" style="display:none">
        <div class="two">
            <label>Vehicle year<input name="vehicle_year" maxlength="4" value="{{ old('vehicle_year') }}">@error('vehicle_year')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Vehicle price ($)<input name="vehicle_value" inputmode="decimal" value="{{ old('vehicle_value') }}">@error('vehicle_value')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <div class="two" style="margin-top:1rem">
            <label>Make<input name="vehicle_make" maxlength="40" value="{{ old('vehicle_make') }}">@error('vehicle_make')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Model<input name="vehicle_model" maxlength="40" value="{{ old('vehicle_model') }}">@error('vehicle_model')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
    </div>
    <label>Deposit the money into<select name="deposit_account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}" data-seg="{{ $a->product->segment }}" @selected((int) old('deposit_account_id') === $a->id)>{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }}</option>@endforeach</select>@error('deposit_account_id')<span class="err">{{ $message }}</span>@enderror</label>
    <button class="btn" type="submit">Submit application</button>
</form>
</div>
@push('scripts')
<script>
(function () {
    var p = document.getElementById('product'), amt = document.getElementById('amount'), term = document.getElementById('term'), est = document.getElementById('est'), veh = document.getElementById('vehicle');
    function pay(a, bps, n) { var r = bps / 120000; return r === 0 ? a / n : a * r / (1 - Math.pow(1 + r, -n)); }
    function sync() {
        var o = p.options[p.selectedIndex], terms = o.dataset.terms.split(',');
        veh.style.display = o.dataset.type === 'auto' ? 'block' : 'none';
        var biz = o.dataset.seg === 'business', rev = o.dataset.rev === '1';
        document.getElementById('equip').style.display = o.dataset.type === 'bequip' ? 'block' : 'none';
        document.getElementById('emprow').style.display = biz ? 'none' : '';
        document.getElementById('inclabel').textContent = biz ? 'Annual business revenue ($)' : 'Annual income before tax ($)';
        document.getElementById('amtlabel').textContent = rev ? 'Credit limit you want ($)' : 'Amount ($)';
        var bi = document.getElementById('bizinfo'); if (bi) bi.hidden = !biz;
        var dep = document.querySelector('[name=deposit_account_id]');
        Array.prototype.forEach.call(dep.options, function (x) { x.hidden = x.disabled = (x.dataset.seg === 'business') !== biz; });
        if (dep.options[dep.selectedIndex] && dep.options[dep.selectedIndex].disabled) { for (var k = 0; k < dep.options.length; k++) { if (!dep.options[k].disabled) { dep.selectedIndex = k; break; } } }
        document.getElementById('term').closest('label').style.display = rev ? 'none' : '';
        Array.prototype.forEach.call(term.options, function (t) { t.hidden = t.disabled = terms.indexOf(t.value) < 0; });
        if (terms.indexOf(term.value) < 0) term.value = terms[0];
        var a = parseFloat(amt.value);
        if (a > 0) {
            var lo = pay(a, +o.dataset.min, +term.value), hi = pay(a, +o.dataset.max, +term.value);
            if (rev) { est.textContent = 'You pay interest on what you draw, plus 1% of the balance each month (at least $25).'; return; }
            est.textContent = 'Estimated payment: $' + lo.toFixed(2) + ' to $' + hi.toFixed(2) + ' a month, depending on your rate.';
        } else est.textContent = '';
    }
    [p, amt, term].forEach(function (e) { e.addEventListener('input', sync); });
    sync();
})();
</script>
@endpush
@endsection
