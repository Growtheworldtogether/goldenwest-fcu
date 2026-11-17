@extends('layouts.portal')
@section('title', 'Spending insights')
@section('band')
<div class="pband-row">
    <div><h1>Spending insights</h1><p class="psub">Where your money went in {{ $month->format('F Y') }}.</p></div>
    <div class="macts"><a class="btn-s plain" href="{{ route('overdraft.index') }}">Overdraft protection</a></div>
</div>
@endsection
@section('content')
@php($cats = $categories)
<nav class="tabs" aria-label="Month"><a href="{{ route('insights.index', ['month' => $prev->format('Y-m')]) }}">&larr; {{ $prev->format('M Y') }}</a>@if ($next)<a href="{{ route('insights.index', ['month' => $next->format('Y-m')]) }}">{{ $next->format('M Y') }} &rarr;</a>@endif</nav>

@foreach ($budgets as $b)
    @if ($b['state'] !== 'ok')
    <div class="notice-bar" role="status"><p><strong>{{ $cats[$b['category']] }}:</strong> {{ $b['state'] === 'over' ? 'you are over your budget' : 'you have used '.$b['percent'].'% of your budget' }} ({{ \App\Support\Money::cents($b['spent'])->format() }} of {{ \App\Support\Money::cents($b['limit'])->format() }}).</p></div>
    @endif
@endforeach

<div class="strip">
    <div><b>{{ \App\Support\Money::cents($spent)->format() }}</b><span>Spent</span></div>
    <div><b>{{ \App\Support\Money::cents($income)->format() }}</b><span>Money in</span></div>
    <div><b>{{ \App\Support\Money::cents($income - $spent)->format() }}</b><span>{{ $income >= $spent ? 'Left over' : 'Over' }}</span></div>
</div>

<div class="cols tcols">
<div>
    <div class="panel-card">
        <h3>Spending by category</h3>
        @if ($byCategory)
            @php($max = max(array_column($byCategory, 'total')))
            @foreach ($byCategory as $c => $v)
                <div class="ibar"><div class="ibar-h"><span>{{ $cats[$c] ?? $c }}</span><span>{{ \App\Support\Money::cents($v['total'])->format() }}</span></div><div class="goalbar"><span style="width:{{ round($v['total'] * 100 / $max) }}%"></span></div></div>
            @endforeach
        @else<p class="fine" style="margin:0">No spending this month.</p>@endif
    </div>
    <div class="table-title"><h2>Purchases</h2></div>
    <div class="panel-card flush">
    @if (count($rows))
    <table class="dtable"><tbody>
    @foreach ($rows as $r)
        <tr><td>{{ $r['tx']->member_description ?? ucfirst(str_replace('_', ' ', $r['tx']->type->value)) }}<small>{{ $r['tx']->effective_date->format('M j') }}</small></td>
        <td>{{ $r['tx']->amount->format() }}</td>
        <td><form method="post" action="{{ route('insights.category', $r['tx']->reference) }}" style="display:flex;gap:.4rem">@csrf<select name="category" aria-label="Category" onchange="this.form.submit()" style="padding:.3rem">@foreach ($cats as $k => $l)<option value="{{ $k }}" @selected($r['category'] === $k)>{{ $l }}</option>@endforeach</select></form></td></tr>
    @endforeach
    </tbody></table>
    @else<div class="empty"><b>No purchases</b>Card purchases, bills and fees show here.</div>@endif
    </div>
</div>
<aside>
    <div class="panel-card">
        <h3>Top merchants</h3>
        @forelse ($merchants as $name => $cents)<p class="item" style="display:flex;justify-content:space-between;margin:.3rem 0"><span>{{ $name }}</span><span>{{ \App\Support\Money::cents($cents)->format() }}</span></p>@empty<p class="fine" style="margin:0">Nothing yet.</p>@endforelse
    </div>
    <div class="panel-card" style="margin-top:1.25rem">
        <h3>Monthly budgets</h3>
        @foreach ($budgets as $b)<p class="item" style="margin:.3rem 0;display:flex;justify-content:space-between"><span>{{ $cats[$b['category']] }}</span><span><span class="chip {{ ['ok' => 'completed', 'near' => 'held', 'over' => 'rejected'][$b['state']] }}">{{ $b['percent'] }}%</span> of {{ \App\Support\Money::cents($b['limit'])->format() }}</span></p>@endforeach
        <form class="sform" method="post" action="{{ route('insights.budget') }}" style="margin-top:.8rem">@csrf
            <label>Category<select name="category">@foreach ($cats as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Limit per month ($, leave empty to remove)<input name="limit" inputmode="decimal"></label>
            <button type="submit" class="plain">Save budget</button>
        </form>
    </div>
</aside>
</div>
@endsection
