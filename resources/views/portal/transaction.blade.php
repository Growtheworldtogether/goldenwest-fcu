@extends('layouts.portal')
@section('title', 'Transaction details')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('activity') }}">&larr; Activity</a></p>
<h1>{{ $rows[0]['title'] ?? 'Transaction' }}</h1>
<p class="psub">{{ $tx->amount->format() }} &middot; {{ $tx->effective_date->format('F j, Y') }}
    @if ($tx->status->value !== 'completed') &middot; <span class="tag {{ $tx->status->value }}">{{ ['pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'][$tx->status->value] }}</span>@endif</p>
@endsection
@section('content')
@php($row = $rows[0] ?? null)

@php($last = $decisions->first())
@if ($last && in_array($last->decision, ['held', 'rejected']))
    <div class="notice-bar" role="status">
        <p><strong>{{ $last->decision === 'held' ? 'Why this is on hold' : 'Why this was declined' }}:</strong> {{ $reasons[$last->reason_code] ?? '' }}</p>
        @if ($last->reason_text)<p>{{ $last->reason_text }}</p>@endif
        @if ($last->hold_until)<p>We expect to finish by {{ $last->hold_until->format('F j') }}.</p>@endif
        <form class="form" method="post" action="{{ route('transactions.contest', $tx->reference) }}" style="margin-top:.9rem">@csrf
            <label>Tell us why you disagree (optional)<textarea name="message" rows="2" maxlength="1000"></textarea></label>
            <button class="btn" type="submit">Contest this decision</button>
        </form>
    </div>
@elseif ($tx->status->value === 'pending')
    <div class="notice-bar" role="status"><p>We are reviewing this payment. The money is set aside and you will see an update here.</p></div>
@endif

<div class="panel-card tx-detail">
    <dl class="kv">
        <dt>Amount</dt><dd>{{ $tx->amount->format() }}</dd>
        @if ($tx->fee->cents > 0)<dt>Fee</dt><dd>{{ $tx->fee->format() }}</dd>@endif
        <dt>Date</dt><dd>{{ $tx->effective_date->format('F j, Y') }}</dd>
        @if ($tx->fromAccount)<dt>From</dt><dd>{{ $accounts->has($tx->from_account_id) ? $accounts[$tx->from_account_id]->nickname.' '.$accounts[$tx->from_account_id]->masked() : 'Another account' }}</dd>@endif
        @if ($tx->toAccount)<dt>To</dt><dd>{{ $accounts->has($tx->to_account_id) ? $accounts[$tx->to_account_id]->nickname.' '.$accounts[$tx->to_account_id]->masked() : 'Another account' }}</dd>@endif
        @if ($tx->memo)<dt>Memo</dt><dd>{{ $tx->memo }}</dd>@endif
        <dt>Reference</dt><dd>{{ $tx->reference }}</dd>
    </dl>
</div>
@endsection
