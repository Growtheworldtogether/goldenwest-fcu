@extends('layouts.portal')
@section('title', 'Announcements')
@section('band')
<h1>Announcements</h1>
<p class="psub">Notices members see on their dashboard. They can dismiss them unless you make one permanent.</p>
@endsection
@section('content')
@php($types = \App\Models\Announcement::TYPES)
<div class="panel-card">
    <h3>New notice</h3>
    <form class="form" method="post" action="{{ route('admin.announcements.store') }}">@csrf
        <label>Title<input name="title" maxlength="120" required value="{{ old('title') }}">@error('title')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Message<textarea name="body" rows="3" maxlength="1000" required>{{ old('body') }}</textarea>@error('body')<span class="err">{{ $message }}</span>@enderror</label>
        <div class="two">
            <label>Type<select name="type">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Who sees it<select name="audience">@foreach ($audiences as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        </div>
        <div class="two">
            <label>Starts (optional)<input type="datetime-local" name="starts_at"></label>
            <label>Ends (optional)<input type="datetime-local" name="ends_at">@error('ends_at')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <label class="chk" style="display:flex;gap:.55rem;align-items:center;font-weight:400"><input type="checkbox" name="dismissible" value="1" checked><span>Members can dismiss it</span></label>
        <button class="btn" type="submit">Publish notice</button>
    </form>
</div>
<div class="table-title"><h2>Notices</h2></div>
<div class="panel-card flush">
@if ($items->count())
<table class="dtable"><thead><tr><th>Notice</th><th>Audience</th><th>Runs</th><th></th></tr></thead><tbody>
@foreach ($items as $a)
    <tr>
        <td><strong>{{ $a->title }}</strong><small>{{ $types[$a->type] }} &middot; {{ \Illuminate\Support\Str::limit($a->body, 90) }}</small></td>
        <td>{{ $audiences[$a->audience] ?? $a->audience }}</td>
        <td>{{ $a->starts_at?->format('M j, g:i a') ?? 'Now' }} to {{ $a->ends_at?->format('M j, g:i a') ?? 'no end' }}<small>{{ $a->starts_at && $a->starts_at->isFuture() ? 'Scheduled' : ($a->ends_at && $a->ends_at->isPast() ? 'Ended' : 'Showing') }}</small></td>
        <td class="go"><form method="post" action="{{ route('admin.announcements.destroy', $a) }}" onsubmit="return confirm('Remove this notice?')">@csrf @method('DELETE')<button class="linkbtn" type="submit">Remove</button></form></td>
    </tr>
@endforeach
</tbody></table>
@else<div class="empty"><b>No notices yet</b>Publish one above.</div>@endif
</div>
@endsection
