@extends('layouts.portal')
@section('title', 'Identity review')
@section('band')
<h1>Identity review</h1>
<p class="psub">Oldest first. Open a document to approve it or tell the member what to fix.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Filter">
    @foreach (['pending' => 'Waiting for review', 'all' => 'Everything'] as $k => $l)
        <a href="{{ route('admin.kyc.index', ['status' => $k]) }}" @if ($filter === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="panel-card flush">
    @if ($docs->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Received</th><th>Member</th><th>Document</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($docs as $d)
            <tr>
                <td>{{ $d->created_at->format('M j, g:i a') }}@if ($d->status === 'pending')<small>{{ $d->created_at->diffForHumans(null, true) }} waiting</small>@endif</td>
                <td><a class="main" href="{{ route('admin.members.show', $d->user) }}">{{ $d->user->name }}</a><small>#{{ $d->user->member_number }}</small></td>
                <td>{{ $d->typeLabel() }}</td>
                <td><span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected'][$d->status] ?? 'pending' }}">{{ ucfirst($d->status) }}</span></td>
                <td class="go"><a class="plink" href="{{ route('admin.kyc.show', $d) }}">{{ $d->status === 'pending' ? 'Review' : 'View' }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
        <div class="empty"><b>Nothing here</b>No documents are waiting.</div>
    @endif
</div>
@endsection
