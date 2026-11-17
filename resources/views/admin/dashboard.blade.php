@extends('layouts.portal')
@section('title', 'Overview')
@section('content')
<h1>Overview</h1>
<p class="psub">What needs attention today.</p>

<div class="stats">
    @can('staff.viewQueue')
        <a class="stat {{ $pending ? 'warn' : '' }}" href="{{ route('admin.queue.index', ['status' => 'pending']) }}"><b>{{ $pending }}</b><span>Pending review</span></a>
        <a class="stat" href="{{ route('admin.queue.index', ['status' => 'held']) }}"><b>{{ $held }}</b><span>On hold</span></a>
        <a class="stat {{ $overdue ? 'bad' : '' }}" href="{{ route('admin.queue.index') }}"><b>{{ $overdue }}</b><span>Waiting over 3 business days</span></a>
    @endcan
    @can('staff.answerInquiries')
        <a class="stat {{ $inquiries ? 'warn' : '' }}" href="{{ route('admin.inquiries.index') }}"><b>{{ $inquiries }}</b><span>Open inquiries</span></a>
    @endcan
    <div class="stat"><b>{{ number_format($members) }}</b><span>Members</span></div>
</div>

<div class="cols">
@can('staff.viewQueue')
<div class="panel-card">
    <h3>Oldest waiting</h3>
    @forelse ($oldest as $tx)
        <ul class="tl"><li><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}</a> <span class="tag {{ $tx->status->value }}">{{ $tx->status->value }}</span><small>Waiting since {{ $tx->created_at->format('M j, g:i a') }}</small></li></ul>
    @empty
        <p style="margin:0">The queue is clear.</p>
    @endforelse
</div>
@endcan
@can('staff.answerInquiries')
<div class="panel-card">
    <h3>Newest inquiries</h3>
    @forelse ($latestInquiries as $i)
        <ul class="tl"><li><strong>{{ $i->name }}</strong> <span class="tag pending">{{ $i->type }}</span><small>{{ $i->topic ?: \Illuminate\Support\Str::limit($i->message, 70) }} &middot; {{ $i->created_at->diffForHumans() }}</small></li></ul>
    @empty
        <p style="margin:0">No open inquiries.</p>
    @endforelse
    <p style="margin:1rem 0 0"><a class="plink" href="{{ route('admin.inquiries.index') }}">Open the inbox</a></p>
</div>
@endcan
</div>
@endsection
