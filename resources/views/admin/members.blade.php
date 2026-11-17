@extends('layouts.portal')
@section('title', 'Members')
@section('content')
<h1>Members</h1>
<p class="psub">Search by name, email, member number, phone or the last digits of an account number.</p>
<form class="sform" method="get" style="grid-template-columns:1fr auto;align-items:end;max-width:40rem;margin-bottom:1.25rem">
    <label>Search<input type="search" name="q" value="{{ $q }}" autofocus></label>
    <button type="submit">Search</button>
</form>
<div class="panel-card flush">
    @if ($members->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Member</th><th>Member #</th><th class="hide-s">Email</th><th class="hide-s">Joined</th></tr></thead>
        <tbody>
        @foreach ($members as $m)
            <tr><td><a class="main" href="{{ route('admin.members.show', $m) }}">{{ $m->name }}</a></td><td>{{ $m->member_number }}</td><td class="hide-s">{{ $m->email }}</td><td class="hide-s">{{ $m->created_at->format('M j, Y') }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="pager" style="padding:.9rem 1.25rem;margin:0;border-top:1px solid #e7ebf6"><span class="fine" style="margin:0">{{ $members->total() }} found</span><span>
        @if ($members->previousPageUrl())<a class="plink" href="{{ $members->previousPageUrl() }}">&larr; Previous</a>@endif
        @if ($members->nextPageUrl()) &nbsp; <a class="plink" href="{{ $members->nextPageUrl() }}">Next &rarr;</a>@endif</span></div>
    @else
        <div class="empty"><b>No members match</b>Try a different name, number or email.</div>
    @endif
</div>
@endsection
