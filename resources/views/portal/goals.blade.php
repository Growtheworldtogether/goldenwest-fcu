@extends('layouts.portal')
@section('title', 'Savings goals')
@section('band')
<div class="pband-row">
    <div><h1>Savings goals</h1><p class="psub">Set money aside for something that matters. It stays yours and shows in your account activity.</p></div>
    <div class="macts"><button type="button" class="btn-s gold" data-open="dlg-goal">New goal</button></div>
</div>
@endsection
@section('content')
@if ($goals->count())
<div class="cols" style="grid-template-columns:repeat(auto-fit,minmax(min(21rem,100%),1fr))">
@foreach ($goals as $g)
    <div class="panel-card">
        <h3 style="margin-bottom:.15rem">{{ $g->name }} @if ($g->status === 'achieved')<span class="chip completed">Reached</span>@endif</h3>
        <p class="fine" style="margin:0 0 .7rem">{{ $g->savedAmount()->format() }} of {{ $g->target()->format() }}@if ($g->target_date) &middot; by {{ $g->target_date->format('M j, Y') }}@endif &middot; from {{ $g->parent->nickname }} {{ $g->parent->masked() }}</p>
        <div class="goalbar" role="progressbar" aria-valuenow="{{ $g->percent() }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $g->percent() }}%"></span></div>
        <form class="sform" method="post" action="{{ route('goals.move', $g) }}" style="margin-top:1rem">@csrf
            <label>Amount ($)<input name="amount" inputmode="decimal" required></label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="submit" name="direction" value="in" class="gold">Save toward goal</button>
                <button type="submit" name="direction" value="out" class="plain">Take out</button>
            </div>
        </form>
        <form class="sform" method="post" action="{{ route('goals.auto', $g) }}" style="margin-top:1rem;border-top:1px solid var(--line);padding-top:1rem">@csrf
            <div class="two"><label>Save automatically ($)<input name="auto_amount" inputmode="decimal" value="{{ $g->auto_amount ? number_format($g->auto_amount / 100, 2, '.', '') : '' }}"></label>
            <label>How often<select name="auto_frequency"><option value="">Off</option><option value="weekly" @selected($g->auto_frequency === 'weekly')>Every week</option><option value="monthly" @selected($g->auto_frequency === 'monthly')>Every month</option></select></label></div>
            @if ($g->auto_next_date)<p class="fine" style="margin:0">Next one on {{ $g->auto_next_date->format('M j') }}.</p>@endif
            <button type="submit" class="plain">Save settings</button>
        </form>
        <form method="post" action="{{ route('goals.close', $g) }}" style="margin-top:.8rem" onsubmit="return confirm('Close this goal? What you saved goes back to {{ $g->parent->nickname }}.')">@csrf<button type="submit" class="linkbtn">Close goal</button></form>
    </div>
@endforeach
</div>
@else
<div class="panel-card"><div class="empty"><b>No goals yet</b>A vacation, an emergency fund, a down payment. Create a goal and start saving toward it.</div></div>
@endif

<dialog id="dlg-goal">
    <div class="dh"><h3>New savings goal</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('goals.store') }}">@csrf
        <label>Goal name<input name="name" maxlength="60" required value="{{ old('name') }}">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Target amount ($)<input name="target" inputmode="decimal" required value="{{ old('target') }}">@error('target')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Target date (optional)<input type="date" name="target_date" value="{{ old('target_date') }}">@error('target_date')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Save from<select name="parent_account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }}</option>@endforeach</select></label>
        <button class="btn-s" type="submit">Create goal</button>
    </form>
</dialog>
@push('scripts')
<script>
document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
document.querySelectorAll('dialog').forEach(function (d) { d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); }); d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
@if ($errors->hasAny(['name', 'target', 'target_date'])) document.getElementById('dlg-goal').showModal(); @endif
</script>
@endpush
@endsection
