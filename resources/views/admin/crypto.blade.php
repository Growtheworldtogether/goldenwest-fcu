@extends('layouts.portal')
@section('title', 'Crypto')
@section('band')
<h1>Crypto</h1>
<p class="psub">Review sends, confirm deposits and manage assets. Wallets are a simulation: nothing real moves.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Sections">
    <a href="{{ route('admin.crypto.index') }}" @if ($tab === 'sends') aria-current="page" @endif>Send requests @if ($counts['sends']) ({{ $counts["sends"] }}) @endif</a>
    <a href="{{ route('admin.crypto.index', ['tab' => 'funding']) }}" @if ($tab === 'funding') aria-current="page" @endif>Funding @if ($counts['funding']) ({{ $counts["funding"] }}) @endif</a>
    <a href="{{ route('admin.crypto.index', ['tab' => 'activity']) }}" @if ($tab === 'activity') aria-current="page" @endif>All activity</a>
    @can('staff.manageAdmin')<a href="{{ route('admin.crypto.index', ['tab' => 'assets']) }}" @if ($tab === 'assets') aria-current="page" @endif>Assets and fees</a>@endcan
</nav>

@if ($tab === 'sends')
<div class="panel-card flush">
@if ($sends->count())
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Member</th><th>Send</th><th>To</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach ($sends as $t)
            <tr>
                <td><a class="main" href="{{ route('admin.members.show', $t->user) }}">{{ $t->user->name }}</a><small>{{ $t->created_at->format('M j, g:i a') }} &middot; {{ $t->reference }}@if ($t->impersonator_id) &middot; requested by staff acting as the member @endif</small></td>
                <td>{{ $t->asset->format($t->amount) }}<small>+ {{ $t->asset->format($t->fee) }} fee @if (($v = $t->asset->usdCents($t->amount)) !== null) &middot; about ${{ number_format($v / 100, 2) }}@endif</small></td>
                <td style="max-width:16rem;word-break:break-all"><small style="display:block">{{ $t->asset->network }}</small>{{ $t->address }}@if ($t->memo)<small>Memo {{ $t->memo }}</small>@endif</td>
                <td><span class="chip {{ $t->status === 'held' ? 'held' : 'pending' }}">{{ $t->statusLabel() }}</span></td>
                <td class="go" style="min-width:16rem">
                    <form method="post" action="{{ route('admin.crypto.send.decide', $t->reference) }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button class="btn-s sm" type="submit">Approve</button></form>
                    @foreach (['reject' => 'Decline', 'hold' => 'Hold'] as $act => $label)
                        @continue($act === 'hold' && $t->status === 'held')
                        <details class="act" style="margin-top:.5rem"><summary>{{ $label }}</summary>
                            <form class="sform" method="post" action="{{ route('admin.crypto.send.decide', $t->reference) }}">@csrf<input type="hidden" name="action" value="{{ $act }}">
                                <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                                <label>Message to the member<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                                <button type="submit" class="plain">{{ $act === 'reject' ? 'Decline send' : 'Put on hold' }}</button></form>
                        </details>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody></table></div>
@else<div class="empty"><b>Nothing waiting</b>No crypto sends need review.</div>@endif
</div>

@elseif ($tab === 'funding')
<div class="table-title"><h2>Deposits to confirm</h2></div>
<div class="panel-card flush">
@if ($deposits->count())
    <table class="dtable"><thead><tr><th>Member</th><th>Member says they sent</th><th></th></tr></thead><tbody>
    @foreach ($deposits as $t)
        <tr>
            <td><a class="main" href="{{ route('admin.members.show', $t->user) }}">{{ $t->user->name }}</a><small>{{ $t->created_at->format('M j, g:i a') }} &middot; {{ $t->reference }}</small></td>
            <td>{{ $t->asset->format($t->amount) }} on {{ $t->asset->network }}@if ($t->description)<small>{{ $t->description }}</small>@endif</td>
            <td class="go" style="min-width:18rem">
                <form class="sform" method="post" action="{{ route('admin.crypto.deposit.decide', $t->reference) }}" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">@csrf<input type="hidden" name="action" value="confirm">
                    <label style="margin:0">Amount that arrived<input name="amount" inputmode="decimal" value="{{ \App\Support\CryptoAmount::format($t->amount, $t->asset->decimals) }}" style="width:9rem"></label>
                    <button class="btn-s sm" type="submit">Confirm</button></form>
                <details class="act" style="margin-top:.5rem"><summary>Decline</summary>
                    <form class="sform" method="post" action="{{ route('admin.crypto.deposit.decide', $t->reference) }}">@csrf<input type="hidden" name="action" value="reject">
                        <label>Reason<select name="reason_code">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                        <label>Message to the member<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                        <button type="submit" class="plain">Decline deposit</button></form></details>
            </td>
        </tr>
    @endforeach
    </tbody></table>
@else<div class="empty"><b>Nothing to confirm</b>No deposits are waiting.</div>@endif
</div>
<div class="table-title"><h2>Address requests</h2></div>
<div class="panel-card flush">
@if ($requests->count())
    <table class="dtable"><thead><tr><th>Member</th><th>Asset</th><th>Set the address</th></tr></thead><tbody>
    @foreach ($requests as $r)
        <tr>
            <td><a class="main" href="{{ route('admin.members.show', $r->user) }}">{{ $r->user->name }}</a><small>{{ $r->created_at->format('M j, g:i a') }}</small></td>
            <td>{{ $r->asset->label() }}</td>
            <td><form class="sform" method="post" action="{{ route('admin.crypto.address', $r->user) }}" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">@csrf<input type="hidden" name="asset_id" value="{{ $r->asset_id }}">
                <label style="margin:0">Address<input name="address" maxlength="120" required spellcheck="false" style="width:18rem"></label>
                <label style="margin:0">Memo<input name="memo" maxlength="60" style="width:7rem"></label>
                <button class="btn-s sm" type="submit">Save</button></form></td>
        </tr>
    @endforeach
    </tbody></table>
@else<div class="empty"><b>No requests</b>Members have not asked for new addresses.</div>@endif
</div>

@elseif ($tab === 'activity')
<div class="panel-card flush">
    <div style="overflow-x:auto"><table class="dtable">
        <thead><tr><th>Date</th><th>Member</th><th>Type</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
        @forelse ($activity as $t)
            <tr><td>{{ $t->created_at->format('M j, Y g:i a') }}<small>{{ $t->reference }}</small></td>
                <td><a class="main" href="{{ route('admin.members.show', $t->user) }}">{{ $t->user->name }}</a></td>
                <td>{{ ucfirst($t->type) }}@if ($t->tx_hash)<small>{{ \Illuminate\Support\Str::limit($t->tx_hash, 20, '...') }}</small>@endif</td>
                <td class="num">{{ $t->asset->format($t->amount) }}</td>
                <td><span class="chip {{ ['pending' => 'pending', 'held' => 'held', 'rejected' => 'rejected', 'completed' => 'completed'][$t->status] ?? 'pending' }}">{{ $t->statusLabel() }}</span></td></tr>
        @empty
            <tr><td colspan="5"><div class="empty"><b>No activity</b></div></td></tr>
        @endforelse
        </tbody></table></div>
</div>
@if ($activity->hasPages())<p class="pager" style="margin-top:1rem">{{ $activity->links() }}</p>@endif

@else
<div class="panel-card flush">
    <table class="dtable"><thead><tr><th>Asset</th><th>Network fee per send</th><th>Dollar price</th><th>Available</th><th></th></tr></thead><tbody>
    @foreach ($assets as $a)
        <tr>
            <td>{{ $a->name }}<small>{{ $a->symbol }} on {{ $a->network }}</small></td>
            <td><input form="asset-{{ $a->id }}" name="network_fee" value="{{ \App\Support\CryptoAmount::format($a->network_fee, $a->decimals) }}" inputmode="decimal" style="width:9rem"> {{ $a->symbol }}</td>
            <td>$<input form="asset-{{ $a->id }}" name="usd_rate" value="{{ $a->usd_rate_cents !== null ? number_format($a->usd_rate_cents / 100, 2, '.', '') : '' }}" inputmode="decimal" style="width:9rem" @disabled($a->live_rate)>
                <label style="display:flex;gap:.4rem;align-items:center;margin-top:.3rem"><input form="asset-{{ $a->id }}" type="checkbox" name="live" value="1" @checked($a->live_rate) @disabled(! $a->coingecko_id)> Live price</label>
                @if ($a->live_rate)<small>Updated {{ $a->rate_updated_at?->diffForHumans() ?? 'never' }}</small>@endif</td>
            <td><label style="display:flex;gap:.4rem;align-items:center"><input form="asset-{{ $a->id }}" type="checkbox" name="enabled" value="1" @checked($a->enabled)> On</label></td>
            <td class="go"><button form="asset-{{ $a->id }}" class="btn-s sm" type="submit">Save</button></td></tr>
    @endforeach
    </tbody></table>
    @foreach ($assets as $a)<form id="asset-{{ $a->id }}" method="post" action="{{ route('admin.crypto.asset', $a) }}" hidden>@csrf @method('PUT')</form>@endforeach
</div>
<form method="post" action="{{ route('admin.crypto.rates') }}" style="margin:1rem 0">@csrf<button class="btn-s" type="submit">Refresh live prices now</button></form>
<p class="fine">Live prices come from CoinGecko and refresh every ten minutes, and when a member opens a crypto page. Turn off Live price on an asset to type a rate by hand. The fee is charged on every send and held with the amount. Dollar prices set the values members see and the manager adjustment limit.</p>
@endif
@endsection
