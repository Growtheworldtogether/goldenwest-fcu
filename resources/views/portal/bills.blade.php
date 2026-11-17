@extends('layouts.portal')
@section('title', 'Bill pay')
@section('band')
<div class="pband-row">
    <div><h1>Bill pay</h1><p class="psub">Pay the companies you owe, once or on a schedule. Payments take up to {{ config('bank.bill_delivery_days') }} business days to arrive.</p></div>
    <div class="macts"><button type="button" class="btn-s plain" data-open="dlg-biller">Add a biller</button></div>
</div>
@endsection
@section('content')
@if ($billers->isEmpty())
    <div class="panel-card"><div class="empty"><b>No billers yet</b>Add a company you pay, such as your electric company, then schedule a payment.</div></div>
@else
<div class="cols tcols">
<div class="panel-card">
    <h3>Pay a bill</h3>
    <form class="sform" method="post" action="{{ route('bills.store') }}">@csrf
        <label>Pay<select name="biller_id">@foreach ($billers as $b)<option value="{{ $b->id }}" @selected((int) old('biller_id') === $b->id)>{{ $b->name }} ({{ $b->account_reference }})</option>@endforeach</select></label>
        <label>From<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }} &middot; {{ $a->available_balance->format() }}</option>@endforeach</select></label>
        <div class="two"><label>Amount ($)<input name="amount" inputmode="decimal" required value="{{ old('amount') }}">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Send on<input type="date" name="send_date" required min="{{ today()->toDateString() }}" value="{{ old('send_date', today()->toDateString()) }}">@error('send_date')<span class="err">{{ $message }}</span>@enderror</label></div>
        <label>Repeat<select name="recurrence"><option value="none">Just once</option><option value="weekly">Every week</option><option value="monthly">Every month</option></select></label>
        <button type="submit" class="gold">Schedule payment</button>
    </form>
    <p class="fine" style="margin-bottom:0">Payments over {{ $review->format() }} are reviewed by our team before they go out.</p>
</div>
<div class="panel-card">
    <h3>Your billers</h3>
    @foreach ($billers as $b)
        <div class="item" style="display:flex;justify-content:space-between;gap:.5rem;align-items:center"><span>{{ $b->name }}<br><span class="fine">{{ $b->categoryLabel() }} &middot; {{ $b->account_reference }}</span></span>
        <form method="post" action="{{ route('bills.billers.destroy', $b) }}" onsubmit="return confirm('Remove {{ $b->name }}?')">@csrf @method('DELETE')<button class="linkbtn" type="submit">Remove</button></form></div>
    @endforeach
</div>
</div>

<div class="table-title"><h2>Scheduled payments</h2></div>
<div class="panel-card flush">
@if ($upcoming->count())
<table class="dtable"><tbody>
@foreach ($upcoming as $p)
    <tr><td>{{ $p->biller->name }}<small>{{ $p->recurrence === 'none' ? 'One time' : ucfirst($p->recurrence) }} &middot; from {{ $p->account->nickname }} {{ $p->account->masked() }}</small></td><td>{{ $p->money()->format() }}</td><td>Sends {{ $p->send_date->format('M j, Y') }}<small>Arrives by {{ $p->estimated_delivery->format('M j') }}</small></td>
    <td class="go"><form method="post" action="{{ route('bills.cancel', $p) }}">@csrf<button class="linkbtn" type="submit">Cancel</button></form></td></tr>
@endforeach
</tbody></table>
@else<div class="empty"><b>Nothing scheduled</b></div>@endif
</div>
@if ($history->count())
<div class="table-title"><h2>Recent payments</h2></div>
<div class="panel-card flush"><table class="dtable"><tbody>
@foreach ($history as $p)
    <tr><td>{{ $p->biller->name }}<small>Sent {{ $p->send_date->format('M j, Y') }}</small></td><td>{{ $p->money()->format() }}</td>
    <td><span class="chip {{ ['delivered' => 'completed', 'processing' => 'held', 'review' => 'pending', 'failed' => 'rejected', 'cancelled' => 'pending'][$p->status] }}">{{ $p->statusLabel() }}</span>@if ($p->status === 'processing')<small>Arrives by {{ $p->estimated_delivery->format('M j') }}</small>@endif @if ($p->failure)<small>{{ $p->failure }}</small>@endif</td></tr>
@endforeach
</tbody></table></div>
@endif
@endif

<dialog id="dlg-biller">
    <div class="dh"><h3>Add a biller</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('bills.billers.store') }}">@csrf
        <label>Company name<input name="name" maxlength="80" required value="{{ old('name') }}">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Type<select name="category">@foreach (config('spending.biller_categories') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <label>Your account number with them<input name="account_reference" maxlength="40" required value="{{ old('account_reference') }}">@error('account_reference')<span class="err">{{ $message }}</span>@enderror</label>
        <button class="btn-s" type="submit">Save biller</button>
    </form>
</dialog>
@push('scripts')
<script>
document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
document.querySelectorAll('dialog').forEach(function (d) { d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); }); d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
@if ($errors->hasAny(['name', 'account_reference'])) document.getElementById('dlg-biller').showModal(); @endif
</script>
@endpush
@endsection
