@extends('layouts.portal')
@section('title', 'Review document')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('admin.kyc.index') }}">&larr; Identity review</a></p>
<h1>{{ $doc->typeLabel() }}</h1>
<p class="psub"><a href="{{ route('admin.members.show', $doc->user) }}" style="color:inherit;text-decoration:underline">{{ $doc->user->name }}</a> &middot; Member #{{ $doc->user->member_number }} &middot; sent {{ $doc->created_at->format('M j, g:i a') }} &middot; <span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected'][$doc->status] ?? 'pending' }}">{{ ucfirst($doc->status) }}</span></p>
@endsection
@section('content')
<div class="cols">
<div class="panel-card">
    <h3>Document</h3>
    @if ($doc->mime === 'application/pdf')
        <p style="margin:0"><a class="btn" href="{{ route('files.kyc', $doc) }}">Download the PDF</a></p><p class="fine">PDFs download instead of opening in the browser.</p>
    @else
        <a href="{{ route('files.kyc', $doc) }}" target="_blank" rel="noopener"><img class="kycimg" src="{{ route('files.kyc', $doc) }}" alt="Uploaded {{ strtolower($doc->typeLabel()) }}"></a>
    @endif
    <p class="fine">Each time a document is opened it is recorded in the audit log.</p>
</div>
<div>
    <div class="panel-card">
        <h3>Member on file</h3>
        <dl class="kv">
            <dt>Name</dt><dd>{{ $doc->user->name }}</dd>
            <dt>Date of birth</dt><dd>{{ $doc->user->dob?->format('M j, Y') ?? 'Not given' }}</dd>
            <dt>Address</dt><dd>{{ $doc->user->address_line1 }}, {{ $doc->user->city }}, {{ $doc->user->state }} {{ $doc->user->postal_code }}</dd>
            @foreach ($others as $o)<dt>{{ $o->typeLabel() }}</dt><dd>{{ ucfirst($o->status) }}</dd>@endforeach
        </dl>
        <p class="fine">Check that the name and, for proof of address, the address match what the member gave us.</p>
    </div>
    @if ($doc->status === 'pending')
    <div class="panel-card decide" style="margin-top:1.25rem">
        <h3>Decide</h3>
        <details class="act" open><summary>Approve</summary>
            <form class="sform" method="post" action="{{ route('admin.kyc.decide', [$doc, 'approve']) }}">@csrf<button type="submit" class="gold">Approve</button></form></details>
        <details class="act"><summary>Reject</summary>
            <form class="sform" method="post" action="{{ route('admin.kyc.decide', [$doc, 'reject']) }}">@csrf
                <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($reasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
                <label>What the member sees<textarea name="reason_text" rows="2" required></textarea>@error('reason_text')<span class="err">{{ $message }}</span>@enderror</label>
                <button type="submit" class="red">Reject</button></form></details>
    </div>
    @endif
    <div class="panel-card" style="margin-top:1.25rem"><h3>Decisions</h3>
        <ul class="tl">@forelse ($doc->decisions as $d)<li><strong>{{ ucfirst($d->decision) }}</strong> by {{ $d->decider->name }}<small>{{ $d->created_at->format('M j, g:i a') }}@if ($d->reason_code) &middot; {{ $allReasons[$d->reason_code] ?? $d->reason_code }}@endif</small>@if ($d->reason_text)<small style="color:var(--ink)">{{ $d->reason_text }}</small>@endif</li>@empty<li>No decisions yet.</li>@endforelse</ul>
    </div>
</div>
</div>
@endsection
