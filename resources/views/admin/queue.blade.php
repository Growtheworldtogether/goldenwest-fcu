@extends('layouts.portal')
@section('title', 'Review queue')
@section('content')
<div class="pagehead"><div><h1>Review queue</h1><p class="psub">Oldest first. Open an item to approve, hold or reject it.</p></div></div>
<nav class="tabs" aria-label="Filter">
    @foreach (['open' => 'Needs action', 'pending' => 'Pending', 'held' => 'On hold', 'all' => 'Everything'] as $k => $l)
        <a href="{{ route('admin.queue.index', ['status' => $k]) }}" @if ($filter === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="panel-card flush">
    @if ($rows->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>{{ $filter === 'all' ? 'Received' : 'Waiting' }}</th><th>Type</th><th>Member</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($rows as $tx)
            @php($owner = $ownerOf[$tx->from_account_id] ?? $ownerOf[$tx->to_account_id] ?? null)
            @php($open = in_array($tx->status->value, ['pending', 'held']))
            <tr>
                <td>@if ($filter === 'all' || ! $open){{ $tx->created_at->format('M j, g:i a') }}@else<span @class(['late' => $tx->created_at->lte(now()->subWeekdays(3))])>{{ $tx->created_at->diffForHumans(null, true) }}</span><small>{{ $tx->created_at->format('M j, g:i a') }}</small>@endif</td>
                <td>{{ str($tx->type->value)->replace('_', ' ')->title() }}@if ($tx->member_description)<small>{{ \Illuminate\Support\Str::limit($tx->member_description, 40) }}</small>@endif</td>
                <td>@if ($owner)<a class="main" href="{{ route('admin.members.show', $owner) }}">{{ $owner->name }}</a>@else Credit union @endif</td>
                <td class="num">{{ $tx->amount->format() }}</td>
                <td><span class="pill {{ $tx->status->value === 'completed' ? 'on' : ($open ? 'warn' : '') }}">{{ ucfirst($tx->status->value) }}</span></td>
                <td class="go"><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $open ? 'Review' : 'View' }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="pager" style="padding:.9rem 1.25rem;margin:0;border-top:1px solid #e7ebf6"><span class="fine" style="margin:0">{{ $rows->total() }} {{ \Illuminate\Support\Str::plural('item', $rows->total()) }}</span><span>
        @if ($rows->previousPageUrl())<a class="plink" href="{{ $rows->previousPageUrl() }}">&larr; Previous</a>@endif
        @if ($rows->previousPageUrl() && $rows->nextPageUrl()) &nbsp;|&nbsp; @endif
        @if ($rows->nextPageUrl())<a class="plink" href="{{ $rows->nextPageUrl() }}">Next &rarr;</a>@endif</span></div>
    @else
        <div class="empty"><b>Nothing here</b>The queue is clear.</div>
    @endif
</div>
@endsection
