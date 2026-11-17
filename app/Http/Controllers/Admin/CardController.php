<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\ReasonCode;
use App\Services\CardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CardController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.approveCards');
        $tab = $request->query('tab') === 'all' ? 'all' : 'requests';

        return view('admin.cards', [
            'tab' => $tab,
            'cards' => Card::with(['user', 'account'])
                ->when($tab === 'requests', fn ($q) => $q->where('status', 'requested')->orderBy('requested_at'), fn ($q) => $q->latest('id'))
                ->paginate(25)->withQueryString(),
            'reasons' => ReasonCode::whereIn('applies_to', ['decision', 'all'])->orderBy('label')->pluck('label', 'code'),
        ]);
    }

    public function decide(Request $request, Card $card, CardService $cards)
    {
        Gate::authorize('staff.approveCards');
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason_code' => ['nullable', 'string'], 'reason_text' => ['nullable', 'string', 'max:500']]);

        try {
            $cards->decide($card, $request->user(), $data['decision'] === 'approve', $data['reason_code'] ?? null, $data['reason_text'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $data['decision'] === 'approve' ? 'Card approved and issued.' : 'Request declined. The member can see the reason.']);
    }

    /** Staff can freeze or cancel a card, for example on a fraud report. */
    public function change(Request $request, Card $card, CardService $cards)
    {
        Gate::authorize('staff.approveCards');
        $action = $request->validate(['action' => ['required', 'in:freeze,unfreeze,cancel']])['action'];

        try {
            $action === 'cancel' ? $cards->cancel($card, $request->user()) : $cards->freeze($card, $request->user(), $action === 'freeze');
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Card updated.']);
    }
}
