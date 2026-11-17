@extends('layouts.portal')
@section('title', 'Tickets')
@section('band')
<h1>Tickets</h1>
<p class="psub">Member requests and contested decisions. Urgent and oldest first.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Filter">
    @foreach ($tabs as $k => $l)<a href="{{ route('admin.tickets.index', array_filter(['tab' => $k, 'q' => $q, 'category' => $category])) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach
</nav>
<form method="get" class="searchform" style="margin-bottom:1rem">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="searchbar" style="border:1px solid var(--line);border-radius:4px;background:#fff">
        <input name="q" value="{{ $q }}" placeholder="Search by reference, title, member name, email or number" style="flex:1;border:0;padding:.7rem .9rem;font:inherit;min-width:0" aria-label="Search tickets">
        <select name="category" aria-label="Category" style="border:0;border-left:1px solid var(--line);padding:.7rem;font:inherit"><option value="">All categories</option>@foreach (config('support.categories') as $k => $l)<option value="{{ $k }}" @selected($category === $k)>{{ $l }}</option>@endforeach</select>
        <button class="btn-s" type="submit">Search</button>
    </div>
</form>
<div class="panel-card flush">
    @if ($tickets->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Waiting</th><th>Ticket</th><th>Member</th><th>Priority</th><th>Assigned</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($tickets as $t)
            <tr>
                <td>{{ $t->last_reply_at?->diffForHumans(null, true) }}<small>{{ $t->last_reply_at?->format('M j, g:i a') }}</small></td>
                <td><a class="main" href="{{ route('admin.tickets.show', $t) }}">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a><small>{{ $t->reference }} &middot; {{ $t->categoryLabel() }}</small></td>
                <td>{{ $t->user->name }}<small>#{{ $t->user->member_number }}</small></td>
                <td>@if (in_array($t->priority, ['high', 'urgent']))<span class="chip rejected">{{ ucfirst($t->priority) }}</span>@else{{ ucfirst($t->priority) }}@endif</td>
                <td>{{ $t->assignee?->name ?? 'Nobody' }}</td>
                <td><span class="chip {{ ['resolved' => 'completed', 'closed' => 'completed', 'awaiting_member' => 'held'][$t->status] ?? 'pending' }}">{{ $t->statusLabel() }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="pager" style="padding:.9rem 1.25rem;margin:0;border-top:1px solid #e7ebf6"><span class="fine" style="margin:0">{{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</span><span>
        @if ($tickets->previousPageUrl())<a class="plink" href="{{ $tickets->previousPageUrl() }}">&larr; Previous</a>@endif
        @if ($tickets->previousPageUrl() && $tickets->nextPageUrl()) &nbsp;|&nbsp; @endif
        @if ($tickets->nextPageUrl())<a class="plink" href="{{ $tickets->nextPageUrl() }}">Next &rarr;</a>@endif</span></div>
    @else
        <div class="empty"><b>Nothing here</b>No tickets match.</div>
    @endif
</div>
@endsection
