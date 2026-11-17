@extends('layouts.portal')
@section('title', 'Fund wallet')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('crypto.index') }}">&larr; Crypto wallet</a></p>
<h1>Fund wallet</h1>
<p class="psub">Send crypto to your personal deposit address, then tell us the amount so we can confirm it.</p>
@endsection
@section('content')
<nav class="tabs" aria-label="Asset">
    @foreach ($assets as $a)<a href="{{ route('crypto.fund', ['asset' => $a->id]) }}" @if ($a->id === $asset->id) aria-current="page" @endif>{{ $a->symbol }}@if ($a->symbol === 'USDC' || $a->symbol === 'USDT') <small>({{ $a->network }})</small>@endif</a>@endforeach
</nav>

@if ($address)
<div class="panel-card cryptofund">
    <div class="qrbox" aria-label="QR code for the {{ $asset->symbol }} deposit address">{!! \App\Support\Qr::svg($address->address, 190) !!}</div>
    <div class="addrside">
        <h3>Your {{ $asset->name }} deposit address</h3>
        <p class="addrbox"><code id="addr">{{ $address->address }}</code></p>
        <p><button class="btn-s" type="button" data-copy="{{ $address->address }}">Copy address</button></p>
        @if ($address->memo)
            <p class="addrbox memo"><span>Memo / tag (required)</span><code>{{ $address->memo }}</code></p>
            <p><button class="btn-s plain" type="button" data-copy="{{ $address->memo }}">Copy memo</button></p>
        @endif
        <div class="notice-bar" role="note"><p>Send only <b>{{ $asset->symbol }}</b> on the <b>{{ $asset->network }}</b> network to this address. Anything sent on another network, or any other asset, cannot be recovered.</p></div>
    </div>
</div>

<div class="table-title"><h2>I sent funds</h2></div>
<form class="panel-card form" method="post" action="{{ route('crypto.declare') }}" style="max-width:34rem">
    @csrf
    <input type="hidden" name="asset" value="{{ $asset->id }}"><input type="hidden" name="token" value="{{ $token }}">
    <label>Amount you sent ({{ $asset->symbol }})<input name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.00" required>@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Note (optional)<input name="note" value="{{ old('note') }}" maxlength="140">@error('note')<span class="err">{{ $message }}</span>@enderror</label>
    <div><button class="btn" type="submit">Tell us about my deposit</button></div>
    <p class="fine" style="margin:0">A team member confirms each deposit and then adds it to your wallet. Your balance does not change until then.</p>
</form>

@if ($waiting->count())
<div class="table-title"><h2>Waiting for confirmation</h2></div>
<div class="panel-card flush"><table class="dtable"><thead><tr><th>Told us on</th><th class="num">Amount</th></tr></thead><tbody>
    @foreach ($waiting as $w)<tr><td>{{ $w->created_at->format('M j, g:i a') }}<small>{{ $w->reference }}</small></td><td class="num">{{ $asset->format($w->amount) }}</td></tr>@endforeach
</tbody></table></div>
@endif
@elseif ($requested)
<div class="panel-card"><div class="empty"><b>Your address is on its way</b>A team member is setting up your {{ $asset->symbol }} deposit address. It shows here as soon as it is ready.</div></div>
@else
<div class="panel-card"><div class="empty"><b>No {{ $asset->symbol }} address yet</b>
    <form method="post" action="{{ route('crypto.address') }}" style="margin-top:.8rem">@csrf<input type="hidden" name="asset" value="{{ $asset->id }}"><button class="btn" type="submit">Request a {{ $asset->symbol }} address</button></form></div></div>
@endif
@include('portal._crypto-note')
@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
        var done = function () { window.toast && window.toast({type: 'ok', message: 'Copied.'}); };
        if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.copy).then(done); }
        else { var t = document.createElement('textarea'); t.value = b.dataset.copy; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
    });
});
</script>
@endpush
@endsection
