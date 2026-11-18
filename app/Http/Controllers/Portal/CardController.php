<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\PhysicalCardOrder;
use App\Services\CardService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class CardController extends Controller
{
    private function current(Request $request): ?Card
    {
        return Card::with('account')->where('user_id', $request->user()->id)->whereIn('status', ['requested', 'active', 'frozen'])->latest('id')->first();
    }

    private function mine(Request $request, Card $card): Card
    {
        abort_unless($card->user_id === $request->user()->id, 404);

        return $card;
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $card = $this->current($request);

        return view('portal.card', [
            'card' => $card,
            'last' => Card::where('user_id', $user->id)->whereIn('status', ['rejected', 'cancelled'])->latest('id')->first(),
            'checking' => $user->accounts()->where('accounts.status', 'active')->where('accounts.type', 'standard')->whereHas('product', fn ($q) => $q->where('kind', 'share_draft'))->get(),
            'purchases' => $card ? $card->purchases()->limit(10)->get() : collect(),
            'revealed' => session('revealed'),
            'order' => $card ? PhysicalCardOrder::where('card_id', $card->id)->latest('id')->first() : null,
            'maxDaily' => Money::cents((int) config('bank.card_daily_limit')),
            'maxPurchase' => Money::cents((int) config('bank.card_purchase_limit')),
        ]);
    }

    public function request(Request $request, CardService $cards)
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'nickname' => ['nullable', 'string', 'max:40']], ['account_id.required' => 'Choose a checking account.']);
        $card = $cards->request($request->user(), (int) $data['account_id'], $data['nickname'] ?? null);
        // A Super Admin acting as the member does not wait for a review.
        if ($admin = \App\Services\StaffDirectory::actingSuperAdmin()) {
            $cards->decide($card, $admin, true);

            return redirect()->route('card.show')->with('toast', ['type' => 'ok', 'message' => 'Card approved and ready.']);
        }

        return redirect()->route('card.show')->with('toast', ['type' => 'ok', 'message' => 'Request sent. We will let you know once it is reviewed.']);
    }

    /** Showing the full number and security code needs the member's password again. */
    public function reveal(Request $request, Card $card, CardService $cards)
    {
        $this->mine($request, $card);
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Enter your password to see the card details.']);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'That password is not right.'], 'reveal');
        }

        return redirect()->route('card.show')->with('revealed', $cards->reveal($card, $request->user()));
    }

    public function freeze(Request $request, Card $card, CardService $cards)
    {
        $freeze = $request->boolean('freeze');
        if ($fail = $this->guard(fn () => $cards->freeze($this->mine($request, $card), $request->user(), $freeze))) {
            return $fail;
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $freeze ? 'Card frozen. Purchases will be declined until you unfreeze it.' : 'Card is active again.']);
    }

    public function limits(Request $request, Card $card, CardService $cards)
    {
        $data = $request->validate(['daily_limit' => ['nullable', 'numeric', 'min:0'], 'purchase_limit' => ['nullable', 'numeric', 'min:0']]);
        $toCents = fn ($v) => ($v === null || $v === '') ? null : (int) round(((float) $v) * 100);
        $cards->setLimits($this->mine($request, $card), $request->user(), $toCents($data['daily_limit'] ?? null), $toCents($data['purchase_limit'] ?? null));

        return back()->with('toast', ['type' => 'ok', 'message' => 'Limits saved.']);
    }

    public function cancel(Request $request, Card $card, CardService $cards)
    {
        if ($fail = $this->guard(fn () => $cards->cancel($this->mine($request, $card), $request->user()))) {
            return $fail;
        }

        return redirect()->route('card.show')->with('toast', ['type' => 'info', 'message' => 'Your card was cancelled.']);
    }

    public function replace(Request $request, Card $card, CardService $cards)
    {
        $new = null;
        if ($fail = $this->guard(function () use ($request, $card, $cards, &$new) {
            $new = $cards->replace($this->mine($request, $card), $request->user());
        })) {
            return $fail;
        }

        return redirect()->route('card.show')->with('toast', ['type' => 'ok', 'message' => 'New card ending '.$new?->last4.' is ready. The old one no longer works.']);
    }

    public function orderPhysical(Request $request, Card $card, CardService $cards)
    {
        $data = $request->validate([
            'name_on_card' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z .\'-]+$/'],
            'address_line1' => ['required', 'string', 'max:120'], 'address_line2' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'], 'state' => ['required', 'alpha', 'size:2'], 'postal_code' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
        ], ['name_on_card.regex' => 'Use letters only for the name on the card.', 'postal_code.regex' => 'Enter a 5-digit ZIP code.']);
        $data['state'] = strtoupper($data['state']);

        $order = null;
        if ($fail = $this->guard(function () use ($cards, $request, $card, $data, &$order) {
            $order = $cards->orderPhysical($this->mine($request, $card), $request->user(), $data);
        })) {
            return $fail;
        }
        if ($order && ($admin = \App\Services\StaffDirectory::actingSuperAdmin())) {
            $cards->decidePhysical($order, $admin, true);

            return redirect()->route('card.show')->with('toast', ['type' => 'ok', 'message' => 'Physical card approved.']);
        }

        return redirect()->route('card.show')->with('toast', ['type' => 'ok', 'message' => 'Physical card requested. We will review it and ship it to the address you gave.']);
    }

    public function cancelPhysical(Request $request, PhysicalCardOrder $order, CardService $cards)
    {
        if ($fail = $this->guard(fn () => $cards->cancelPhysical($order, $request->user()))) {
            return $fail;
        }

        return back()->with('toast', ['type' => 'info', 'message' => 'Physical card order cancelled.']);
    }

    /** Runs a card change; a refused one comes back as a toast instead of an error page. */
    private function guard(callable $work): ?\Illuminate\Http\RedirectResponse
    {
        try {
            $work();
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return null;
    }
}
