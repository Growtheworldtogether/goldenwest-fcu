@extends('layouts.portal')
@section('title', 'Inquiries')
@section('content')
<h1>Inquiries</h1>
<p class="psub">Messages and appointment requests from the website.</p>
<nav class="tabs"><a href="{{ route('admin.inquiries.index') }}" @if ($show === 'open') aria-current="page" @endif>Open</a><a href="{{ route('admin.inquiries.index', ['show' => 'handled']) }}" @if ($show === 'handled') aria-current="page" @endif>Handled</a></nav>
<div class="panel-card flush">
@forelse ($inquiries as $i)
    <article class="inq">
        <div>
            <h3>{{ $i->name }} <span class="pill">{{ ucfirst($i->type) }}</span></h3>
            <p class="imeta"><a class="plink" href="mailto:{{ $i->email }}">{{ $i->email }}</a>@if ($i->phone) &middot; {{ $i->phone }}@endif @if ($i->topic) &middot; {{ $i->topic }}@endif @if ($i->location) &middot; {{ $i->location }}@endif @if ($i->preferred_when) &middot; {{ $i->preferred_when }}@endif</p>
        </div>
        <div class="when">{{ $i->created_at->format('M j, g:i a') }}<br>
            @if (! $i->handled_at)<form method="post" action="{{ route('admin.inquiries.handle', $i) }}" style="margin-top:.4rem">@csrf<button class="btn-s plain sm" type="submit">Mark handled</button></form>@else Handled {{ $i->handled_at->format('M j') }}@endif
        </div>
        @if ($i->message)<p class="msg">{{ $i->message }}</p>@endif
    </article>
@empty
    <div class="empty"><b>{{ $show === 'open' ? 'No open inquiries' : 'Nothing handled yet' }}</b>Messages from the website's contact and appointment forms show up here.</div>
@endforelse
</div>
@if ($inquiries->hasPages())<div class="pager" style="margin-top:1rem"><span></span><span>@if ($inquiries->previousPageUrl())<a class="plink" href="{{ $inquiries->previousPageUrl() }}">&larr; Newer</a>@endif @if ($inquiries->nextPageUrl()) &nbsp; <a class="plink" href="{{ $inquiries->nextPageUrl() }}">Older &rarr;</a>@endif</span></div>@endif
@endsection
