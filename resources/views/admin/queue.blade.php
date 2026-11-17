@extends('layouts.portal')
@section('title', 'Review queue')
@section('content')
<h1>Review queue</h1>
<p class="psub">Transactions waiting for a decision, oldest first.</p>
<nav class="tabs" aria-label="Filter">
    @foreach (['open' => 'Needs action', 'pending' => 'Pending', 'held' => 'On hold', 'all' => 'Everything'] as $k => $l)
        <a href="{{ route('admin.queue.index', ['status' => $k]) }}" @if ($filter === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="panel-card" style="padding:.5rem 1rem 1rem">
    @if ($rows->count())
    <div style="overflow-x:auto"><table class="txtable">
        <thead><tr><th>Received</th><th>Type</th><th>Member</th><th style="text-align:right">Amount</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($rows as $tx)
            @php($owner = $ownerOf[$tx->from_account_id] ?? $ownerOf[$tx->to_account_id] ?? null)
            <tr>
                <td style="white-space:nowrap">{{ $tx->created_at->format('M j, g:i a') }}</td>
                <td><a href="{{ route('admin.queue.show', $tx->reference) }}">{{ str($tx->type->value)->replace('_', ' ')->title() }}</a></td>
                <td>@if ($owner)<a href="{{ route('admin.members.show', $owner) }}" style="font-weight:400">{{ $owner->name }}</a>@else Credit union @endif</td>
                <td class="amt">{{ $tx->amount->format() }}</td>
                <td><span class="tag {{ $tx->status->value === 'completed' ? 'ok' : $tx->status->value }}">{{ $tx->status->value }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="pager"><span class="fine" style="margin:0">{{ $rows->total() }} total</span><span>
        @if ($rows->previousPageUrl())<a class="plink" href="{{ $rows->previousPageUrl() }}">&larr; Previous</a>@endif
        @if ($rows->nextPageUrl()) &nbsp; <a class="plink" href="{{ $rows->nextPageUrl() }}">Next &rarr;</a>@endif</span></div>
    @else
        <p style="padding:1.25rem 1rem;margin:0">Nothing here. The queue is clear.</p>
    @endif
</div>
@endsection
