@extends('layouts.portal')
@section('title', 'Verify your identity')
@section('band')
<h1>Verify your identity</h1>
<p class="psub">Upload your documents once. A person at {{ brand('short_name') }} reviews them, usually within one business day.
    <span class="chip {{ ['verified' => 'completed', 'rejected' => 'rejected'][$status] ?? 'pending' }}">{{ ['verified' => 'Verified', 'rejected' => 'Needs attention', 'pending' => 'Not verified yet'][$status] ?? ucfirst($status) }}</span></p>
@endsection
@section('content')
@if ($status === 'verified')
    <div class="notice-bar" role="status"><p>You are verified. You can deposit checks and move money from another bank.</p></div>
@endif
<div class="cols">
@foreach ($types as $type)
    @php($label = \App\Models\KycDocument::TYPES[$type])
    @php($doc = $docs->get($type))
    @php($last = $doc?->decisions->sortByDesc('id')->first())
    <div class="panel-card">
        <h3>{{ $label }}</h3>
        <p class="fine" style="margin-top:0">{{ ['government_id' => 'A driver license, state ID or passport. The photo and the name must be easy to read.', 'proof_of_address' => 'A recent utility bill, bank statement or lease that shows your name and home address.', 'business_document' => 'Articles of organization or incorporation, a business license, or an IRS letter that shows the business name and EIN.'][$type] }}</p>
        @if ($doc)
            <p style="margin:.4rem 0"><span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected'][$doc->status] ?? 'pending' }}">{{ ['approved' => 'Approved', 'rejected' => 'Not accepted', 'pending' => 'In review'][$doc->status] }}</span>
                <small>{{ $doc->original_name }}, sent {{ $doc->created_at->format('M j') }} &middot; <a class="plink" href="{{ route('files.kyc', $doc) }}" target="_blank" rel="noopener">View</a></small></p>
            @if ($doc->status === 'rejected' && $last)
                <div class="notice-bar" role="status"><p><strong>Why:</strong> {{ $reasons[$last->reason_code] ?? '' }}</p>@if ($last->reason_text)<p>{{ $last->reason_text }}</p>@endif<p>Please upload a new one.</p></div>
            @endif
        @endif
        @if (! $doc || $doc->status !== 'approved')
        <form class="form" method="post" action="{{ route('kyc.store') }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <label>{{ $doc ? 'Upload a new file' : 'Choose a file' }}<input type="file" name="file" accept="image/png,image/jpeg,image/webp,application/pdf" required>@error('file')<span class="err">{{ $message }}</span>@enderror</label>
            <button class="btn" type="submit">Upload</button>
        </form>
        @endif
    </div>
@endforeach
</div>
<p class="fine">PNG, JPG, WebP or PDF, up to 8 MB. Your documents are stored privately and only staff who review identity can open them.</p>
@endsection
