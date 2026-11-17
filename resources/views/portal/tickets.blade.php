@extends('layouts.portal')
@section('title', 'Support')
@section('band')
<div class="pband-row">
    <div><h1>Support</h1><p class="psub">Ask us anything about your accounts. We reply here and send you an email when we do.</p></div>
    <div class="macts"><a class="btn-s gold" href="{{ route('tickets.create') }}">New request</a></div>
</div>
@endsection
@section('content')
<div class="panel-card flush">
    @if ($tickets->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Request</th><th>About</th><th>Last update</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($tickets as $t)
            <tr>
                <td><a class="main" href="{{ route('tickets.show', $t->reference) }}">{{ $t->subject }}</a><small>{{ $t->reference }}</small></td>
                <td>{{ $t->categoryLabel() }}</td>
                <td>{{ $t->last_reply_at?->diffForHumans() ?? $t->created_at->diffForHumans() }}</td>
                <td><span class="chip {{ ['resolved' => 'completed', 'closed' => 'completed', 'awaiting_member' => 'held'][$t->status] ?? 'pending' }}">{{ ['awaiting_staff' => 'With our team', 'open' => 'With our team', 'awaiting_member' => 'Waiting for you', 'resolved' => 'Resolved', 'closed' => 'Closed'][$t->status] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>No requests yet</b>When you ask us something it shows here with our replies.</div>
    @endif
</div>
@if ($tickets->hasPages())<div class="pager" style="margin-top:1rem"><span></span><span>@if ($tickets->previousPageUrl())<a class="plink" href="{{ $tickets->previousPageUrl() }}">&larr; Newer</a>@endif @if ($tickets->nextPageUrl()) &nbsp; <a class="plink" href="{{ $tickets->nextPageUrl() }}">Older &rarr;</a>@endif</span></div>@endif
<p class="fine">For something urgent, such as a card or account you do not recognize, call Customer Care at {{ brand('support_phone') }}.</p>
@endsection
