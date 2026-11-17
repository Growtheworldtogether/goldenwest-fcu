@extends('layouts.portal')
@section('title', 'Ticket '.$ticket->reference)
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('activity') }}">&larr; Activity</a></p>
<h1>{{ $ticket->subject }}</h1>
<p class="psub">Ticket {{ $ticket->reference }} &middot; opened {{ $ticket->created_at->format('F j, Y') }} &middot; <span class="chip pending">{{ ['open' => 'Open', 'awaiting_staff' => 'With our team', 'awaiting_member' => 'Waiting for you', 'resolved' => 'Resolved', 'closed' => 'Closed'][$ticket->status] ?? $ticket->status }}</span></p>
@endsection
@section('content')
<div class="panel-card">
    @foreach ($ticket->memberMessages as $m)
        <div class="msg"><p class="fine" style="margin:0 0 .25rem"><strong>{{ $m->author->id === auth()->id() ? 'You' : brand('short_name').' team' }}</strong> &middot; {{ $m->created_at->format('M j, g:i a') }}</p><p style="margin:0;white-space:pre-line">{{ $m->body }}</p></div>
    @endforeach
    @if ($ticket->transaction)<p style="margin:1rem 0 0"><a class="plink" href="{{ route('transactions.show', $ticket->transaction->reference) }}">View the transaction</a></p>@endif
</div>
<p class="fine">A {{ brand('short_name') }} team member will review this. You can also call Customer Care at {{ brand('support_phone') }}.</p>
@endsection
