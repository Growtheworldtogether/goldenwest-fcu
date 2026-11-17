@extends('layouts.portal')
@section('title', 'Crypto')
@section('band')
<div class="pband-row">
    <div><h1>Crypto wallet</h1><p class="psub">Hold, fund and send crypto assets alongside your accounts.</p></div>
    <div class="macts"><a class="btn-s" href="{{ route('crypto.fund') }}">Fund wallet</a> <a class="btn-s gold" href="{{ route('crypto.send') }}">Send</a></div>
</div>
@endsection
@section('content')
<div class="strip">
    <div><b>${{ number_format($estimate / 100, 2) }}</b><span>Total value</span></div>
    <div><b>{{ $wallets->filter(fn ($w) => $w->balance > 0)->count() }}</b><span>Assets with a balance</span></div>
    <div><b>{{ $history->whereIn('status', ['pending', 'held'])->count() }}</b><span>Waiting for review</span></div>
</div>

<div class="panel-card flush">
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Asset</th><th class="num">Value</th><th class="num">Available</th><th></th></tr></thead>
        <tbody>
        @foreach ($assets as $a)
            @php($w = $wallets->get($a->id))
            @php($bal = $w?->balance ?? 0)
            <tr>
                <td>{{ $a->name }}<small>{{ $a->symbol }} on {{ $a->network }}</small></td>
                <td class="num">{{ $a->usd($bal) }}@if ($a->hasRate())<small>{{ $a->format($bal) }}</small>@endif</td>
                <td class="num">{{ $a->usd($w?->available() ?? 0) }}@if ($w && $w->held > 0)<small>{{ $a->usd($w->held) }} set aside</small>@endif</td>
                <td class="go"><a class="plink" href="{{ route('crypto.fund', ['asset' => $a->id]) }}">Fund</a> &middot; <a class="plink" href="{{ route('crypto.send', ['asset' => $a->id]) }}">Send</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="table-title"><h2>Activity</h2></div>
<div class="panel-card flush">
@if ($history->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Date</th><th>What</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($history as $t)
            @php($last = in_array($t->status, ['rejected', 'held'], true) ? $t->lastDecision() : null)
            <tr>
                <td>{{ $t->created_at->format('M j, Y') }}<small>{{ $t->reference }}</small></td>
                <td>
                    @if ($t->type === 'send')Send to {{ \Illuminate\Support\Str::limit($t->address, 18, '...') }}<small>Network fee {{ $t->asset->usd($t->fee) }}@if ($t->tx_hash) &middot; hash {{ \Illuminate\Support\Str::limit($t->tx_hash, 14, '...') }}@endif</small>
                    @elseif ($t->type === 'fund')Deposit<small>{{ $t->description }}</small>
                    @else{{ $t->description }}@endif
                    @if ($last?->reason_text)<small>{{ $last->reason_text }}</small>@endif
                </td>
                <td class="num">{{ $t->type === 'send' ? '-' : '' }}{{ $t->asset->usd($t->amount) }}@if ($t->asset->hasRate())<small>{{ $t->asset->format($t->amount) }}</small>@endif</td>
                <td><span class="chip {{ ['pending' => 'pending', 'held' => 'held', 'rejected' => 'rejected', 'completed' => 'completed'][$t->status] ?? 'pending' }}">{{ $t->statusLabel() }}</span></td>
                <td class="go">@if ($t->type === 'send' && $t->status === 'pending')<form method="post" action="{{ route('crypto.cancel', $t->reference) }}">@csrf<button class="linkbtn" type="submit">Cancel</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@else
    <div class="empty"><b>No activity yet</b>Fund your wallet to get started.</div>
@endif
</div>
@include('portal._crypto-note')
@endsection
