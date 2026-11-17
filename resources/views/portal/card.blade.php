@extends('layouts.portal')
@section('title', 'Virtual card')
@section('band')
<h1>Virtual card</h1>
<p class="psub">A card number for online shopping, tied to your checking account. Freeze it any time.</p>
@endsection
@section('content')
@php($dec = fn ($v) => $v ? number_format($v / 100, 2, '.', '') : '')

@if (! $card)
    @if ($last && $last->status === 'rejected')
        @php($d = $last->decisions()->latest('id')->first())
        <div class="notice-bar" role="status"><p><strong>Your last request was not approved.</strong> {{ $d?->reason_text }}</p></div>
    @endif
    <div class="panel-card">
        <h2>Request a virtual card</h2>
        <p class="fine" style="margin-top:0">We review every request, usually within one business day. Once approved, the card appears here ready to use.</p>
        @if (auth()->user()->kyc_status !== 'verified')
            <p>Before we can issue a card we need to verify your identity. <a class="plink" href="{{ route('kyc.show') }}">Verify your identity</a></p>
        @elseif ($checking->isEmpty())
            <p>A virtual card connects to a checking account. <a class="plink" href="{{ route('accounts.create') }}">Open one</a> to get started.</p>
        @else
        <form class="form" method="post" action="{{ route('card.request') }}">@csrf
            <label>Pay from<select name="account_id">@foreach ($checking as $a)<option value="{{ $a->id }}">{{ $a->nickname ?? $a->product->name }} {{ $a->masked() }}</option>@endforeach</select>@error('account_id')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Card name (optional)<input name="nickname" maxlength="40" placeholder="Online shopping" value="{{ old('nickname') }}"></label>
            <button class="btn" type="submit">Request card</button>
        </form>
        @endif
    </div>
@elseif ($card->status === 'requested')
    <div class="panel-card"><h2>Request under review</h2><p style="margin:0">We received your request on {{ $card->requested_at->format('F j, Y') }}. We will reply here and you will see the card as soon as it is approved.</p></div>
@else
    <div class="cardtop">
        <div class="vcard {{ $card->status === 'frozen' ? 'frozen' : '' }}" aria-label="Your virtual card">
            <div class="vc-head"><p class="vc-brand">{{ brand('short_name') }}</p><p class="vc-type">Debit</p></div>
            <p class="vc-chip" aria-hidden="true"></p>
            <p class="vc-num">{{ $revealed ? $revealed['number'] : $card->maskedNumber() }}</p>
            <div class="vc-row"><span>{{ strtoupper(auth()->user()->name) }}</span><span>{{ $card->expiry() }}@if ($revealed) &nbsp; CVV {{ $revealed['cvv'] }}@endif</span></div>
            @if ($card->status === 'frozen')<p class="vc-flag">Frozen</p>@endif
        </div>
        <div class="panel-card cardinfo">
            <h3>{{ $card->nickname ?: 'Virtual card' }}</h3>
            <dl class="kv">
                <dt>Status</dt><dd><span class="chip {{ $card->status === 'frozen' ? 'held' : 'completed' }}">{{ $card->status === 'frozen' ? 'Frozen' : 'Active' }}</span></dd>
                <dt>Pays from</dt><dd>{{ $card->account->nickname ?? $card->account->product->name }} {{ $card->account->masked() }}</dd>
                <dt>Daily limit</dt><dd>{{ $card->dailyLimit()->format() }}</dd>
                <dt>Per purchase</dt><dd>{{ $card->purchaseLimit()->format() }}</dd>
            </dl>
            <div class="cardbtns">
                <form method="post" action="{{ route('card.freeze', $card) }}">@csrf<input type="hidden" name="freeze" value="{{ $card->status === 'frozen' ? 0 : 1 }}"><button class="btn-s plain sm" type="submit">{{ $card->status === 'frozen' ? 'Unfreeze' : 'Freeze card' }}</button></form>
                @if ($revealed)
                    <a class="btn-s plain sm" href="{{ route('card.show') }}">Hide details</a>
                @else
                    <button type="button" class="btn-s plain sm" data-open="dlg-reveal">Show card details</button>
                @endif
            </div>
        </div>
    </div>

    <div class="cols tcols" style="margin-top:1.25rem">
        <div>
            <div class="panel-card flush">
                <div class="grouphead"><h2>Recent card activity</h2></div>
                @if ($purchases->count())
                <table class="dtable"><tbody>
                @foreach ($purchases as $p)
                    <tr><td>{{ $p->merchant }}<small>{{ $p->created_at->format('M j, g:i a') }}</small></td>
                        <td>{{ $p->money()->format() }}</td>
                        <td><span class="chip {{ ['settled' => 'completed', 'authorized' => 'held', 'declined' => 'rejected'][$p->status] ?? 'pending' }}">{{ ['settled' => 'Posted', 'authorized' => 'On hold', 'declined' => 'Declined', 'released' => 'Released'][$p->status] }}</span>@if ($p->decline_reason)<small>{{ \App\Services\CardService::declineText($p->decline_reason) }}</small>@endif</td></tr>
                @endforeach
                </tbody></table>
                @else<div class="empty"><b>No card activity yet</b>Purchases and holds show here.</div>@endif
            </div>
        </div>
        <aside>
            <div class="panel-card">
                <h3>Physical card</h3>
                @if ($order && $order->status === 'requested')
                    <p style="margin-top:0"><span class="chip pending">Under review</span></p>
                    <p class="fine" style="margin-top:0">Requested {{ $order->created_at->format('M j') }}. We will ship it to {{ $order->addressLine() }}.</p>
                    <form method="post" action="{{ route('card.physical.cancel', $order) }}">@csrf<button class="linkbtn" type="submit">Cancel order</button></form>
                @elseif ($order && $order->status === 'shipped')
                    <p style="margin-top:0"><span class="chip completed">On its way</span></p>
                    <p class="fine" style="margin:0">Shipped {{ $order->shipped_at->format('M j') }} to {{ $order->addressLine() }}. Allow 7 to 10 business days.</p>
                @else
                    @if ($order && $order->status === 'rejected')<p class="fine" style="margin-top:0"><strong>Your last order was not approved.</strong> {{ $order->decline_note }}</p>@endif
                    <p class="fine" style="margin-top:0">Want a card for stores and ATMs? We mail one that works with the same account and limits.</p>
                    <button type="button" class="btn-s plain sm" data-open="dlg-physical">Request physical card</button>
                @endif
            </div>
            <div class="panel-card" style="margin-top:1.25rem">
                <h3>Spending limits</h3>
                <form class="sform" method="post" action="{{ route('card.limits', $card) }}">@csrf
                    <label>Daily limit ($)<input name="daily_limit" inputmode="decimal" value="{{ $dec($card->daily_limit ?? $maxDaily->cents) }}">@error('daily_limit')<span class="err">{{ $message }}</span>@enderror</label>
                    <label>Per purchase ($)<input name="purchase_limit" inputmode="decimal" value="{{ $dec($card->purchase_limit ?? $maxPurchase->cents) }}">@error('purchase_limit')<span class="err">{{ $message }}</span>@enderror</label>
                    <p class="fine" style="margin:0">Up to {{ $maxDaily->format() }} a day and {{ $maxPurchase->format() }} a purchase.</p>
                    <button type="submit" class="plain">Save limits</button>
                </form>
            </div>
        </aside>
    </div>

    <div class="panel-card cardlost">
        <div><h3 style="margin:0">Lost or compromised?</h3><p class="fine" style="margin:.2rem 0 0">Get a new number right away, or cancel the card for good.</p></div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <form method="post" action="{{ route('card.replace', $card) }}">@csrf<button class="btn-s plain sm" type="submit">Replace card</button></form>
            <form method="post" action="{{ route('card.cancel', $card) }}" onsubmit="return confirm('Cancel this card? This cannot be undone.')">@csrf<button class="btn-s plain sm" type="submit">Cancel card</button></form>
        </div>
    </div>

    <dialog id="dlg-reveal">
        <div class="dh"><h3>Show card details</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
        <form class="sform" method="post" action="{{ route('card.reveal', $card) }}">@csrf
            <label>Enter your password<input type="password" name="password" autocomplete="current-password" required>@error('password', 'reveal')<span class="err">{{ $message }}</span>@enderror</label>
            <button class="btn-s" type="submit">Show details</button>
        </form>
    </dialog>
    <dialog id="dlg-physical">
        <div class="dh"><h3>Request a physical card</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
        @php($u = auth()->user())
        <form class="sform" method="post" action="{{ route('card.physical', $card) }}">@csrf
            <label>Name on card<input name="name_on_card" maxlength="40" required value="{{ old('name_on_card', $u->name) }}">@error('name_on_card')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Mailing address<input name="address_line1" required value="{{ old('address_line1', $u->address_line1) }}">@error('address_line1')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Apartment or suite (optional)<input name="address_line2" value="{{ old('address_line2') }}"></label>
            <label>City<input name="city" required value="{{ old('city', $u->city) }}">@error('city')<span class="err">{{ $message }}</span>@enderror</label>
            <div class="two"><label>State<input name="state" maxlength="2" required value="{{ old('state', $u->state) }}">@error('state')<span class="err">{{ $message }}</span>@enderror</label>
            <label>ZIP code<input name="postal_code" required value="{{ old('postal_code', $u->postal_code) }}">@error('postal_code')<span class="err">{{ $message }}</span>@enderror</label></div>
            <button class="btn-s" type="submit">Send request</button>
        </form>
    </dialog>
    @push('scripts')
    <script>
    document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
    document.querySelectorAll('dialog').forEach(function (d) {
        d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); });
        d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    });
    @if ($errors->reveal->any()) document.getElementById('dlg-reveal').showModal(); @endif
    @if ($errors->hasAny(['name_on_card', 'address_line1', 'city', 'state', 'postal_code'])) document.getElementById('dlg-physical').showModal(); @endif
    </script>
    @endpush
@endif
@endsection
