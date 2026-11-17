@extends('layouts.portal')
@section('title', 'Audit log')
@section('content')
<h1>Audit log</h1>
<p class="psub">Every staff action, decision, lock and sign-in. Entries cannot be edited or removed.</p>
<form class="filters" method="get">
    <label>Action contains<input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="for example: restriction"></label>
    <label>Person<input name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Name or email"></label>
    <label>About<select name="subject"><option value="">Anything</option>@foreach ($subjects as $s)<option @selected(($filters['subject'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
    <label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <label style="grid-template-columns:auto 1fr;align-items:center;gap:.5rem"><input type="checkbox" name="acting" value="1" @checked(! empty($filters['acting'])) style="min-height:0;width:auto"> Acting as member only</label>
    <button class="btn" type="submit">Filter</button>
</form>
<div class="panel-card" style="padding:.5rem 1rem 1rem">
    <div style="overflow-x:auto"><table class="txtable">
        <thead><tr><th>When</th><th>Action</th><th>By</th><th class="hide-s">About</th><th class="hide-s">Details</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td style="white-space:nowrap">{{ $r->created_at->format('M j, g:i:s a') }}</td>
                <td><span class="mono">{{ $r->action }}</span></td>
                <td>{{ $users[$r->actor_id] ?? 'System' }}@if ($r->impersonator_id)<span class="sub">acting: {{ $users[$r->impersonator_id] ?? 'staff' }}</span>@endif</td>
                <td class="hide-s">{{ $r->subject_type }} {{ $r->subject_id }}</td>
                <td class="hide-s mono">{{ \Illuminate\Support\Str::limit(json_encode($r->new ?? $r->old), 90) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No entries match.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pager"><span class="fine" style="margin:0">{{ $rows->total() }} entries</span><span>
        @if ($rows->previousPageUrl())<a class="plink" href="{{ $rows->previousPageUrl() }}">&larr; Newer</a>@endif
        @if ($rows->nextPageUrl()) &nbsp; <a class="plink" href="{{ $rows->nextPageUrl() }}">Older &rarr;</a>@endif</span></div>
</div>
@endsection
