@extends('layouts.portal')
@section('title', 'System')
@section('band')
<h1>System</h1>
<p class="psub">Which version of the platform this site is running, and updates from GitHub. Only a Super Admin sees this page.</p>
@endsection
@section('content')
@php
    $name = fn ($tag) => \App\Http\Controllers\Admin\SystemController::versionName((string) $tag);
@endphp
<div class="panel-card">
    <h2>Version</h2>
    <dl class="kvlist">
        <dt>Installed</dt>
        <dd>@if ($current){{ $name($current['tag']) }}@if ($current['commit']) <span class="fine">({{ $current['commit'] }})</span>@endif @else <span class="fine">Not recorded. This copy was not installed from a release package.</span>@endif</dd>
        <dt>Newest on GitHub</dt>
        <dd>@if ($latest){{ $name($latest['tag']) }}@else <span class="fine">Not checked yet.</span>@endif</dd>
        <dt>Folder layout</dt>
        <dd>{{ $layout === 'public_html' ? 'public_html (app folder beside it)' : 'Standard (domain points at app/public)' }}</dd>
    </dl>
    @if ($last)
        <p class="fine">Last {{ $last['action'] === 'restore' ? 'restore' : 'update' }}: {{ $name($last['to']) }}, {{ \Illuminate\Support\Carbon::parse($last['at'])->timezone(config('app.timezone'))->format('M j, Y g:i a T') }}.@if ($last['problem']) Problem: {{ $last['problem'] }}@endif</p>
    @endif
</div>

<div class="panel-card" style="margin-top:1.25rem">
    <h2>Updates</h2>
    @if ($job)
        <p>An update is in progress or was interrupted.</p>
        <div class="actions" style="display:flex;gap:.75rem;flex-wrap:wrap">
            <a class="btn-s" href="{{ route('admin.system.progress') }}">Continue the update</a>
            <form method="post" action="{{ route('admin.system.cancel') }}" onsubmit="return confirm('Stop the update and put the site back as it was?')">@csrf<button class="btn-s ghost" type="submit">Stop and undo</button></form>
        </div>
    @else
        @if ($newer)
            <p><strong>A new version is ready:</strong> {{ $name($latest['tag']) }}.</p>
            @if (! empty($latest['notes']))<p class="fine" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($latest['notes'], 400) }}</p>@endif
            <p class="fine">Installing replaces the program files. Your settings, uploaded files and database are kept, and a backup of every changed file is made first. The site shows "back in a minute" to visitors for a few seconds while the files are swapped.</p>
            <form method="post" action="{{ route('admin.system.update') }}" onsubmit="return confirm('Install this update now?')">@csrf<button class="btn-s" type="submit">Install update</button></form>
        @elseif ($latest)
            <p>You have the latest version.</p>
        @endif
        <form method="post" action="{{ route('admin.system.check') }}" style="margin-top:.75rem">@csrf<button class="btn-s {{ $newer ? 'ghost' : '' }}" type="submit">Check for updates</button></form>
    @endif
    <p class="fine" style="margin-top:1rem">Updates come from the Releases page of github.com/{{ $repo }}@if ($private) (using the saved access token)@endif. To make a new one, open Actions there, choose Build release zip, then Run workflow.</p>
</div>

@if ($backup)
<div class="panel-card" style="margin-top:1.25rem">
    <h2>Previous version</h2>
    <p>The last update changed files from {{ $name($backup['from']) }} to {{ $name($backup['to']) }}.@if ($backup['restored_at']) It has been undone.@endif</p>
    @unless ($backup['restored_at'])
        <p class="fine">Restore puts the old program files back. Member records and money are not touched, and database changes the update made stay in place.</p>
        <form method="post" action="{{ route('admin.system.restore') }}" onsubmit="return confirm('Put the previous version back?')">@csrf<button class="btn-s ghost" type="submit">Restore previous version</button></form>
    @endunless
</div>
@endif
@endsection
