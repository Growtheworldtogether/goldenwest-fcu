@extends('layouts.portal')
@section('title', 'Request '.$ticket->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('tickets.index') }}">&larr; Support</a></p>
<h1>{{ $ticket->subject }}</h1>
<p class="psub">{{ $ticket->reference }} &middot; {{ $ticket->categoryLabel() }} &middot; opened {{ $ticket->created_at->format('F j, Y') }} &middot;
    <span class="chip {{ ['resolved' => 'completed', 'closed' => 'completed', 'awaiting_member' => 'held'][$ticket->status] ?? 'pending' }}">{{ ['awaiting_staff' => 'With our team', 'open' => 'With our team', 'awaiting_member' => 'Waiting for you', 'resolved' => 'Resolved', 'closed' => 'Closed'][$ticket->status] }}</span></p>
@endsection
@section('content')
<div class="panel-card tthread">
    @foreach ($ticket->memberMessages as $m)
        @php($mine = $m->author_id === auth()->id())
        <div class="tmsg {{ $mine ? 'mine' : 'staff' }}">
            <p class="fine" style="margin:0 0 .25rem"><strong>{{ $mine ? 'You' : ($m->author->first_name ?? 'Our team').', '.brand('short_name').' team' }}</strong> &middot; {{ $m->created_at->format('M j, g:i a') }}</p>
            <p style="margin:0;white-space:pre-line">{{ $m->body }}</p>
            @include('portal._attachments', ['message' => $m])
        </div>
    @endforeach
    @if ($ticket->transaction)<p style="margin:1rem 0 0"><a class="plink" href="{{ route('transactions.show', $ticket->transaction->reference) }}">View the related transaction</a></p>@endif
</div>

@if ($ticket->status === 'closed')
    <div class="notice-bar" role="status"><p>This request is closed. If you still need help, please <a class="plink" href="{{ route('tickets.create') }}">open a new request</a>.</p></div>
@else
<div class="panel-card" style="margin-top:1.25rem">
    <h3>{{ $ticket->status === 'resolved' ? 'Still need help? Reply to reopen this' : 'Reply' }}</h3>
    <form class="form" method="post" action="{{ route('tickets.reply', $ticket->reference) }}" enctype="multipart/form-data">
        @csrf
        <label>Your message<textarea name="body" rows="4" maxlength="5000" required>{{ old('body') }}</textarea>@error('body')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Attach photos or a PDF (optional)<input type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf">@error('files.*')<span class="err">{{ $message }}</span>@enderror</label>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap"><button class="btn" type="submit">Send reply</button></div>
    </form>
    @if ($ticket->status !== 'resolved')
    <form method="post" action="{{ route('tickets.resolve', $ticket->reference) }}" style="margin-top:.9rem">@csrf<button type="submit" class="linkbtn">This is sorted, mark it resolved</button></form>
    @endif
</div>
@endif
<p class="fine">You can also call Customer Care at {{ brand('support_phone') }}. We are open {{ brand('support_hours') }}.</p>
@endsection
