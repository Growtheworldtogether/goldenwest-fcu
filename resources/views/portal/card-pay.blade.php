@extends('layouts.portal')
@section('title', 'Card checkout')
@section('band')
<p class="crumbs2"><a class="plink" href="{{ route('card.show') }}">&larr; Virtual card</a></p>
<h1>Card checkout</h1>
<p class="psub">Card ending {{ $card->last4 }} &middot; {{ $card->status === 'frozen' ? 'frozen' : 'active' }}</p>
@endsection
@section('content')
<div class="cols tcols" style="grid-template-columns:minmax(0,1fr) minmax(0,1.2fr)">
<div>
    <div class="panel-card">
        <h3>Pay a merchant</h3>
        <form class="form" method="post" action="{{ route('card.pay.store') }}">@csrf
            <label>Merchant<select name="merchant">@foreach ($merchants as $m)<option @selected(old('merchant') === $m)>{{ $m }}</option>@endforeach</select></label>
            <label>Amount ($)<input name="amount" inputmode="decimal" required value="{{ old('amount') }}">@error('amount')<span class="err">{{ $message }}</span>@enderror</label>
            <label>How should it go through?
                <select name="mode"><option value="pay">Pay now (authorize and post)</option><option value="hold">Put a hold only, like a hotel or gas pump</option></select>
            </label>
            <button class="btn" type="submit">Submit</button>
        </form>
        <p class="fine" style="margin-bottom:0">A hold sets the money aside. It leaves your balance when the merchant completes the purchase, or comes back if they do not.</p>
    </div>
</div>
<div>
    <div class="table-title"><h2>Attempts</h2></div>
    <div class="panel-card flush">
        @if ($attempts->count())
        <table class="dtable"><tbody>
        @foreach ($attempts as $p)
            <tr><td>{{ $p->merchant }}<small>{{ $p->created_at->format('M j, g:i a') }}</small></td><td>{{ $p->money()->format() }}</td>
            <td><span class="chip {{ ['settled' => 'completed', 'authorized' => 'held', 'declined' => 'rejected'][$p->status] ?? 'pending' }}">{{ ['settled' => 'Posted', 'authorized' => 'On hold', 'declined' => 'Declined', 'released' => 'Released'][$p->status] }}</span>@if ($p->decline_reason)<small>{{ \App\Services\CardService::declineText($p->decline_reason) }}</small>@endif
                @if ($p->status === 'authorized')
                <div style="display:flex;gap:.5rem;margin-top:.4rem">
                    <form method="post" action="{{ route('card.settle', $p) }}">@csrf<button class="linkbtn" type="submit">Complete purchase</button></form>
                    <form method="post" action="{{ route('card.release', $p) }}">@csrf<button class="linkbtn" type="submit">Release hold</button></form>
                </div>@endif
            </td></tr>
        @endforeach
        </tbody></table>
        @else<div class="empty"><b>Nothing yet</b>Your attempts show here.</div>@endif
    </div>
</div>
</div>
@endsection
