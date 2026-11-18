@extends('layouts.portal')
@section('title', 'Overdraft protection')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('insights.index') }}">&larr; Insights</a></p>
<h1>Overdraft protection</h1>
<p class="psub">If a payment would take your checking below zero, we move the shortfall from a savings account you choose, so the payment still goes through.</p>
@endsection
@section('content')
@forelse ($checking as $c)
<div class="panel-card" style="margin-bottom:1.25rem">
    <h3>{{ $c->nickname ?? $c->product->name }} {{ $c->masked() }}</h3>
    <p class="fine" style="margin-top:0">Available {{ $c->available_balance->format() }}. {{ $c->overdraft_account_id ? 'Protected by '.$c->overdraftAccount->nickname.' '.$c->overdraftAccount->masked().'.' : 'Not protected right now.' }}</p>
    <form class="sform" method="post" action="{{ route('overdraft.update', $c) }}">@csrf @method('PUT')
        <label>Transfer from<select name="source_id"><option value="">No protection</option>@foreach ($sources->filter(fn ($s) => $s->product->isBusiness() === $c->product->isBusiness()) as $s)<option value="{{ $s->id }}" @selected($c->overdraft_account_id === $s->id)>{{ $s->nickname ?? $s->product->name }} {{ $s->masked() }} &middot; {{ $s->available_balance->format() }}</option>@endforeach</select></label>
        <button type="submit" class="plain">Save</button>
    </form>
</div>
@empty
<div class="panel-card"><div class="empty"><b>No checking account</b>Overdraft protection links a savings account to checking.</div></div>
@endforelse
<p class="fine">{{ $fee->isZero() ? 'There is no fee for overdraft protection transfers.' : 'Each transfer costs '.$fee->format().'.' }} The transfer covers only the amount you are short.</p>

<div class="table-title"><h2>Recent protection transfers</h2></div>
<div class="panel-card flush">
@if ($recent->count())
<table class="dtable"><tbody>@foreach ($recent as $t)<tr><td>{{ $t->effective_date->format('M j, Y') }}<small>From {{ $t->fromAccount?->nickname }} to {{ $t->toAccount?->nickname }}</small></td><td>{{ $t->amount->format() }}</td></tr>@endforeach</tbody></table>
@else<div class="empty"><b>None yet</b>You will see a transfer here any time protection covers a payment.</div>@endif
</div>
@endsection
