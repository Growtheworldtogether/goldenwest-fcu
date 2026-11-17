@extends('layouts.portal')
@section('title', 'Inquiries')
@section('content')
<h1>Inquiries</h1>
<p class="psub">Messages and appointment requests from the website.</p>
<nav class="tabs"><a href="{{ route('admin.inquiries.index') }}" @if ($show === 'open') aria-current="page" @endif>Open</a><a href="{{ route('admin.inquiries.index', ['show' => 'handled']) }}" @if ($show === 'handled') aria-current="page" @endif>Handled</a></nav>
@forelse ($inquiries as $i)
    <div class="panel-card" style="margin-bottom:1rem">
        <p style="margin:0"><strong>{{ $i->name }}</strong> <span class="tag pending">{{ $i->type }}</span> <span class="fine" style="margin:0">{{ $i->created_at->format('M j, g:i a') }}</span></p>
        <p class="fine" style="margin:.2rem 0 .6rem"><a class="plink" href="mailto:{{ $i->email }}">{{ $i->email }}</a>@if ($i->phone) &middot; {{ $i->phone }}@endif @if ($i->topic) &middot; {{ $i->topic }}@endif @if ($i->location) &middot; {{ $i->location }}@endif @if ($i->preferred_when) &middot; {{ $i->preferred_when }}@endif</p>
        @if ($i->message)<p style="margin:0 0 .8rem;white-space:pre-line">{{ $i->message }}</p>@endif
        @if (! $i->handled_at)
            <form method="post" action="{{ route('admin.inquiries.handle', $i) }}">@csrf<button class="btn-s plain" type="submit">Mark as handled</button></form>
        @else
            <p class="fine" style="margin:0">Handled {{ $i->handled_at->format('M j, g:i a') }}</p>
        @endif
    </div>
@empty
    <div class="panel-card"><p style="margin:0">Nothing here.</p></div>
@endforelse
{{ $inquiries->links() }}
@endsection
