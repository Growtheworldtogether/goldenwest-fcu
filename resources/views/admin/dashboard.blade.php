@extends('layouts.portal')
@section('title', 'Overview')
@section('band')
<h1>Overview</h1>
<p class="psub">{{ now()->format('l, F j') }}</p>
@endsection
@section('content')

<div class="strip">
    @can('staff.viewQueue')
        <a href="{{ route('admin.queue.index', ['status' => 'pending']) }}"><b>{{ $pending }}</b><span>Pending review</span></a>
        <a href="{{ route('admin.queue.index', ['status' => 'held']) }}"><b>{{ $held }}</b><span>On hold</span></a>
        <a href="{{ route('admin.queue.index') }}" @class(['alert' => $overdue > 0])><b>{{ $overdue }}</b><span>Over 3 business days</span></a>
    @endcan
    @can('staff.answerInquiries')
        <a href="{{ route('admin.tickets.index') }}"><b>{{ $tickets }}</b><span>Tickets to answer</span></a>
        <a href="{{ route('admin.inquiries.index') }}"><b>{{ $inquiries }}</b><span>Open inquiries</span></a>
    @endcan
    <div><b>{{ number_format($members) }}</b><span>Members</span></div>
</div>

@can('staff.viewQueue')
<div class="sechead"><h2>Waiting longest</h2><a class="plink" href="{{ route('admin.queue.index') }}">Open the queue</a></div>
<div class="panel-card flush" style="margin-bottom:2rem">
    @if ($oldest->count())
    <table class="dtable">
        <thead><tr><th>Waiting</th><th>Type</th><th class="hide-s">Member</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        @foreach ($oldest as $tx)
            @php($owner = $ownerOf[$tx->from_account_id] ?? $ownerOf[$tx->to_account_id] ?? null)
            <tr>
                <td><span @class(['late' => $tx->created_at->lte(now()->subWeekdays(3))])>{{ $tx->created_at->diffForHumans(null, true) }}</span><small>{{ ucfirst($tx->status->value) }}</small></td>
                <td>{{ str($tx->type->value)->replace('_', ' ')->title() }}</td>
                <td class="hide-s">{{ $owner?->name ?? 'Credit union' }}</td>
                <td class="num">{{ $tx->amount->format() }}</td>
                <td class="go"><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">Review</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @else
        <div class="empty"><b>Nothing waiting</b>The queue is clear.</div>
    @endif
</div>
@endcan

@can('staff.answerInquiries')
<div class="sechead"><h2>New inquiries</h2><a class="plink" href="{{ route('admin.inquiries.index') }}">Open the inbox</a></div>
<div class="panel-card flush">
    @if ($latestInquiries->count())
    <table class="dtable"><tbody>
        @foreach ($latestInquiries as $i)
            <tr><td><a class="main" href="{{ route('admin.inquiries.index') }}">{{ $i->name }}</a><small>{{ $i->topic ?: \Illuminate\Support\Str::limit($i->message, 80) }}</small></td><td><span class="pill">{{ ucfirst($i->type) }}</span></td><td class="num" style="font-weight:400;color:var(--muted)">{{ $i->created_at->diffForHumans() }}</td></tr>
        @endforeach
    </tbody></table>
    @else
        <div class="empty"><b>No open inquiries</b>New messages from the website show up here.</div>
    @endif
</div>
@endcan
@endsection
