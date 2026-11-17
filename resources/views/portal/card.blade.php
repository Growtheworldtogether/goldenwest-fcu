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
    <div class="vcard {{ $card->status === 'frozen' ? 'frozen' : '' }}" aria-label="Your virtual card">
        <p class="vc-brand">{{ brand('short_name') }}</p>
        <p class="vc-num">{{ $revealed ? $revealed['number'] : $card->maskedNumber() }}</p>
        <div class="vc-row"><span>{{ auth()->user()->name }}</span><span>Exp {{ $card->expiry() }}@if ($revealed) &nbsp; CVV {{ $revealed['cvv'] }}@endif</span></div>
        @if ($card->status === 'frozen')<p class="vc-flag">Frozen</p>@endif
    </div>
    <p class="fine">{{ $card->nickname ?: 'Virtual card' }} &middot; pays from {{ $card->account->nickname ?? $card->account->product->name }} {{ $card->account->masked() }}</p>

    <div class="cols tcols">
    <div>
        <div class="panel-card">
            <h3>Use your card</h3>
            <p class="fine" style="margin-top:0">Pay a merchant, put a hold on funds, and see how a purchase moves through your account.</p>
            <p style="margin:0"><a class="btn-s plain" href="{{ route('card.pay') }}">Go to checkout</a></p>
        </div>
        <div class="table-title"><h2>Recent card activity</h2></div>
        <div class="panel-card flush">
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
            <h3>Card controls</h3>
            <form method="post" action="{{ route('card.freeze', $card) }}">@csrf<input type="hidden" name="freeze" value="{{ $card->status === 'frozen' ? 0 : 1 }}"><button class="btn-s plain" type="submit">{{ $card->status === 'frozen' ? 'Unfreeze card' : 'Freeze card' }}</button></form>
            @unless ($revealed)
            <form class="sform" method="post" action="{{ route('card.reveal', $card) }}" style="margin-top:1rem">@csrf
                <label>Password, to show the full number<input type="password" name="password" autocomplete="current-password" required>@error('password', 'reveal')<span class="err">{{ $message }}</span>@enderror</label>
                <button type="submit" class="plain">Show card details</button>
            </form>
            @endunless
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
        <div class="panel-card" style="margin-top:1.25rem">
            <h3>Lost or compromised?</h3>
            <p class="fine" style="margin-top:0">Replace it for a new number right away, or cancel it for good.</p>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <form method="post" action="{{ route('card.replace', $card) }}">@csrf<button class="btn-s plain sm" type="submit">Replace card</button></form>
                <form method="post" action="{{ route('card.cancel', $card) }}" onsubmit="return confirm('Cancel this card? This cannot be undone.')">@csrf<button class="btn-s plain sm" type="submit">Cancel card</button></form>
            </div>
        </div>
    </aside>
    </div>
@endif
@endsection
