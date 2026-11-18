@extends('layouts.portal')
@section('title', 'Beneficiaries')
@section('band')
<h1>Beneficiaries</h1>
<p class="psub">Choose who receives each account if you pass away. They can receive it without going through probate, subject to the account agreement.</p>
@endsection
@section('content')
@include('portal._people-tabs')
@forelse ($accounts as $a)
    @php($list = $lists[$a->id])
    <div class="panel-card">
        <div class="pband-row" style="align-items:center">
            <div><h3 style="margin:0">{{ $a->nickname }} <small style="font-weight:400;color:var(--muted)">{{ $a->masked() }}</small></h3></div>
            <a class="btn-s {{ $list->isEmpty() ? 'gold' : '' }}" href="{{ route('beneficiaries.edit', $a) }}">{{ $list->isEmpty() ? 'Add beneficiaries' : 'Edit' }}</a>
        </div>
        @if ($list->isEmpty())
            <p style="margin:.6rem 0 0;color:var(--muted)">No beneficiaries named.</p>
        @else
            <table class="dtable stackl" style="margin-top:.6rem"><thead><tr><th>Name</th><th>Relationship</th><th>Born</th><th>Tier</th><th class="num">Share</th></tr></thead><tbody>
            @foreach ($list as $b)
                <tr><td data-label="Name">{{ $b->name }}</td><td data-label="Relationship">{{ \App\Services\BeneficiaryService::RELATIONSHIPS[$b->relationship] ?? $b->relationship }}</td><td data-label="Born">{{ $b->dob->format('M j, Y') }}</td><td data-label="Tier">{{ \App\Services\BeneficiaryService::TIERS[$b->tier] }}</td><td class="num" data-label="Share">{{ $b->share_percent }}%</td></tr>
            @endforeach
            </tbody></table>
        @endif
        @if ($a->users()->count() > 1)<p class="fine" style="margin:.6rem 0 0">This is a joint account. Any holder can change its beneficiaries.</p>@endif
    </div>
@empty
    <div class="panel-card"><div class="empty"><b>No accounts yet</b>Open an account to name beneficiaries.</div></div>
@endforelse
<p class="fine">Primary beneficiaries receive the account first. Contingent beneficiaries receive it only if no primary beneficiary is living. Each group must add up to 100%.</p>
@endsection
