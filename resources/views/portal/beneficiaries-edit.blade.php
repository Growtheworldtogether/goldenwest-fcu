@extends('layouts.portal')
@section('title', 'Beneficiaries')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('beneficiaries.index') }}">&larr; Beneficiaries</a></p>
<h1>{{ $account->nickname }} beneficiaries</h1>
<p class="psub">{{ $account->product->name }} {{ $account->masked() }}</p>
@endsection
@section('content')
@php
    $rows = old('rows', $current->map(fn ($b) => ['name' => $b->name, 'relationship' => $b->relationship, 'dob' => $b->dob->format('Y-m-d'), 'share_percent' => $b->share_percent, 'tier' => $b->tier])->all());
    $rows = array_values($rows);
    $slots = 12;
    $filled = max(count($rows), 1);
@endphp
<form class="panel-card form" method="post" action="{{ route('beneficiaries.update', $account) }}" autocomplete="off" style="max-width:62rem">
    @csrf @method('PUT')
    @if ($errors->any())<div class="notice-bar" role="alert"><p>@foreach ($errors->all() as $m){{ $m }} @endforeach</p></div>@endif
    <p style="margin:0">Add each person with their share. Primary shares must total 100%. If you add contingent beneficiaries, their shares must total 100% as well. Leave everything empty to clear the list.</p>
    @for ($i = 0; $i < $slots; $i++)
        @php($r = $rows[$i] ?? [])
        <fieldset class="benrow" data-row @if ($i >= $filled + 1) hidden @endif style="border:1px solid var(--line,#d9dfdc);border-radius:4px;padding:.9rem;margin:0">
            <legend style="padding:0 .4rem">Person {{ $i + 1 }}</legend>
            <div class="cols" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:.8rem">
                <label>Full name<input name="rows[{{ $i }}][name]" value="{{ $r['name'] ?? '' }}" maxlength="120"></label>
                <label>Relationship<select name="rows[{{ $i }}][relationship]"><option value="">Choose</option>@foreach (\App\Services\BeneficiaryService::RELATIONSHIPS as $k => $l)<option value="{{ $k }}" @selected(($r['relationship'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
                <label>Date of birth<input type="date" name="rows[{{ $i }}][dob]" value="{{ $r['dob'] ?? '' }}"></label>
                <label>Group<select name="rows[{{ $i }}][tier]">@foreach (\App\Services\BeneficiaryService::TIERS as $k => $l)<option value="{{ $k }}" @selected(($r['tier'] ?? 'primary') === $k)>{{ $l }}</option>@endforeach</select></label>
                <label>Share (%)<input name="rows[{{ $i }}][share_percent]" value="{{ $r['share_percent'] ?? '' }}" inputmode="numeric" maxlength="3"></label>
            </div>
        </fieldset>
    @endfor
    <div><button type="button" class="btn-s plain" id="more">Add another person</button></div>
    <div style="display:flex;gap:.8rem"><button class="btn" type="submit">Save beneficiaries</button><a class="btn-s plain" href="{{ route('beneficiaries.index') }}">Cancel</a></div>
</form>
@push('scripts')
<script>
document.getElementById('more').addEventListener('click', function () {
    var next = document.querySelector('[data-row][hidden]');
    if (next) { next.hidden = false; } if (!document.querySelector('[data-row][hidden]')) { this.hidden = true; }
});
</script>
@endpush
@endsection
