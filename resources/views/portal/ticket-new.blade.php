@extends('layouts.portal')
@section('title', 'New request')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('tickets.index') }}">&larr; Support</a></p>
<h1>New request</h1>
<p class="psub">Tell us what happened. A {{ brand('short_name') }} team member will reply here.</p>
@endsection
@section('content')
<div class="panel-card">
    <form class="form" method="post" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
        @csrf
        <label>What is it about?
            <select name="category" required>@foreach (config('support.categories') as $k => $l)<option value="{{ $k }}" @selected(old('category', $selected ? 'transaction' : null) === $k)>{{ $l }}</option>@endforeach</select>
            @error('category')<span class="err">{{ $message }}</span>@enderror
        </label>
        @if ($transactions->count())
        <label>Related transaction (optional)
            <select name="transaction"><option value="">None</option>@foreach ($transactions as $t)<option value="{{ $t->reference }}" @selected(old('transaction', $selected) === $t->reference)>{{ $t->effective_date->format('M j') }} - {{ $t->member_description ?? str($t->type->value)->replace('_', ' ')->title() }} - {{ $t->amount->format() }}</option>@endforeach</select>
            @error('transaction')<span class="err">{{ $message }}</span>@enderror
        </label>
        @endif
        <label>Short title<input name="subject" value="{{ old('subject') }}" maxlength="160" required>@error('subject')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Your message<textarea name="body" rows="6" maxlength="5000" required>{{ old('body') }}</textarea>@error('body')<span class="err">{{ $message }}</span>@enderror</label>
        <label>Attach photos or a PDF (optional, up to {{ config('support.max_attachments') }})<input type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp,application/pdf">@error('files')<span class="err">{{ $message }}</span>@enderror @error('files.*')<span class="err">{{ $message }}</span>@enderror</label>
        <button class="btn" type="submit">Send request</button>
    </form>
    <p class="fine" style="margin-top:1rem">Please do not include your full account number, password or a one-time code. We will never ask for them.</p>
</div>
@endsection
