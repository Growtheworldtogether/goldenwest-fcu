@extends('layouts.portal')
@section('title', 'Accounts')
@section('content')
<h1>Welcome back, {{ auth()->user()->first_name ?? auth()->user()->name }}</h1>
<p class="psub">Member #{{ auth()->user()->member_number }} &middot; Total in your accounts <strong>{{ $total->format() }}</strong></p>

@foreach ($waiting as $tx)
    @php($d = $tx->decisions->sortByDesc('id')->first())
    <div class="notice-bar" role="status">
        <p><strong>{{ $tx->status->value === 'held' ? 'On hold' : 'Under review' }}:</strong> {{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}@if ($tx->member_description), {{ $tx->member_description }}@endif</p>
        @if ($d && $d->decision === 'held')
            <p>{{ $reasons[$d->reason_code] ?? 'Under review' }}. {{ $d->reason_text }} @if ($d->hold_until) We expect to finish by {{ $d->hold_until->format('M j') }}.@endif</p>
        @else
            <p>We are reviewing this payment. The money is set aside and you will see an update here.</p>
        @endif
        <p><a class="plink" href="{{ route('transactions.show', $tx->reference) }}">View details</a></p>
    </div>
@endforeach

<div class="pcards">
    @foreach ($accounts as $a)
        <article class="acct">
            <span class="kind">{{ str($a->product->kind)->replace('_', ' ') }}</span>
            <h3>{{ $a->nickname }}</h3>
            <span class="num">Account {{ $a->masked() }}</span>
            <span class="bal">{{ $a->balance->format() }}</span>
            <span class="avail">Available {{ $a->available_balance->format() }}@if ($a->held_balance->cents > 0) &middot; {{ $a->held_balance->format() }} on hold @endif</span>
            <div class="actions"><a href="{{ route('accounts.show', $a) }}">View activity</a><a href="{{ route('transfers.create', ['from' => $a->id]) }}">Transfer</a></div>
        </article>
    @endforeach
</div>
<p><a class="plink" href="{{ route('accounts.create') }}">+ Open another account (free)</a></p>

<h2 style="margin-top:2rem">Recent activity</h2>
<div class="panel-card" style="padding:.5rem 1rem">
    @if (count($recent))
        <table class="txtable">
            <tbody>
            @foreach ($recent as $r)
                <tr>
                    <td style="white-space:nowrap;width:1%">{{ $r['tx']->effective_date->format('M j') }}</td>
                    <td><a href="{{ route('transactions.show', $r['tx']->reference) }}">{{ $r['title'] }}</a><span class="sub">{{ $r['account']->nickname }} {{ $r['account']->masked() }}</span></td>
                    <td class="amt {{ $r['cents'] > 0 ? 'in' : '' }}">{{ $r['cents'] > 0 ? '+' : '-' }}@money(abs($r['cents']))</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p style="margin:.5rem .75rem 1rem"><a class="plink" href="{{ route('activity') }}">See all activity</a></p>
    @else
        <p style="padding:1.25rem .75rem;margin:0">Nothing yet. Make a deposit or transfer to get started.</p>
    @endif
</div>
@endsection
