<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CryptoAddress;
use App\Models\CryptoAsset;
use App\Models\CryptoTransaction;
use App\Models\ReasonCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\CryptoRates;
use App\Services\CryptoService;
use App\Support\CryptoAmount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CryptoController extends Controller
{
    private function bad(InvalidArgumentException $e)
    {
        return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
    }

    public function index(Request $request)
    {
        Gate::authorize('staff.approveCrypto');
        $tab = in_array($request->query('tab'), ['funding', 'activity', 'assets'], true) ? $request->query('tab') : 'sends';

        return view('admin.crypto', [
            'tab' => $tab,
            'counts' => [
                'sends' => CryptoTransaction::where('type', 'send')->whereIn('status', ['pending', 'held'])->count(),
                'funding' => CryptoTransaction::where('type', 'fund')->where('status', 'pending')->count() + CryptoAddress::where('status', 'requested')->count(),
            ],
            'sends' => $tab === 'sends' ? CryptoTransaction::with(['user', 'asset'])->where('type', 'send')->whereIn('status', ['pending', 'held'])->orderBy('id')->get() : collect(),
            'deposits' => $tab === 'funding' ? CryptoTransaction::with(['user', 'asset'])->where('type', 'fund')->where('status', 'pending')->orderBy('id')->get() : collect(),
            'requests' => $tab === 'funding' ? CryptoAddress::with(['user', 'asset'])->where('status', 'requested')->orderBy('id')->get() : collect(),
            'activity' => $tab === 'activity' ? CryptoTransaction::with(['user', 'asset'])->latest('id')->paginate(30)->withQueryString() : null,
            'assets' => $tab === 'assets' ? CryptoAsset::orderBy('id')->get() : collect(),
            'reasons' => ReasonCode::whereIn('applies_to', ['decision', 'all'])->orderBy('label')->pluck('label', 'code'),
        ]);
    }

    public function decideSend(Request $request, CryptoTransaction $tx, CryptoService $crypto)
    {
        Gate::authorize('staff.approveCrypto');
        $d = $request->validate(['action' => ['required', 'in:approve,reject,hold'], 'reason_code' => ['nullable', 'string', 'exists:reason_codes,code'], 'reason_text' => ['nullable', 'string', 'max:500']]);
        try {
            $crypto->decideSend($tx, $request->user(), $d['action'], $d['reason_code'] ?? null, $d['reason_text'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->bad($e);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => ['approve' => 'Send approved. The wallet was debited.', 'reject' => 'Send declined. The amount went back to the member\'s available balance.', 'hold' => 'Send put on hold. The amount stays set aside.'][$d['action']]]);
    }

    public function decideDeposit(Request $request, CryptoTransaction $tx, CryptoService $crypto)
    {
        Gate::authorize('staff.approveCrypto');
        $d = $request->validate(['action' => ['required', 'in:confirm,reject'], 'amount' => ['nullable', 'string', 'max:30'], 'reason_code' => ['nullable', 'string', 'exists:reason_codes,code'], 'reason_text' => ['nullable', 'string', 'max:500']]);
        try {
            if ($d['action'] === 'confirm') {
                $units = ! empty($d['amount']) ? CryptoAmount::parse($d['amount'], $tx->asset->decimals) : null;
                $crypto->confirmFunding($tx, $request->user(), $units);
            } else {
                if (empty($d['reason_text'])) {
                    throw new InvalidArgumentException('Give the member a reason.');
                }
                $crypto->rejectFunding($tx, $request->user(), $d['reason_code'] ?? null, $d['reason_text']);
            }
        } catch (InvalidArgumentException $e) {
            return $this->bad($e);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $d['action'] === 'confirm' ? 'Deposit confirmed and added to the wallet.' : 'Deposit declined.']);
    }

    public function address(Request $request, User $user, CryptoService $crypto)
    {
        Gate::authorize('staff.approveCrypto');
        abort_if($user->isAdmin(), 404);
        $d = $request->validate(['asset_id' => ['required', 'exists:crypto_assets,id'], 'address' => ['required', 'string', 'max:120'], 'memo' => ['nullable', 'string', 'max:60']]);
        try {
            $crypto->assignAddress($user, CryptoAsset::findOrFail($d['asset_id']), $d['address'], $d['memo'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->bad($e);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Deposit address saved. The member can see it now.']);
    }

    public function funding(Request $request, User $user, CryptoService $crypto)
    {
        Gate::authorize('staff.approveCrypto');
        abort_if($user->isAdmin(), 404);
        $d = $request->validate(['asset_id' => ['required', 'exists:crypto_assets,id'], 'amount' => ['required', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:140']]);
        $asset = CryptoAsset::findOrFail($d['asset_id']);
        try {
            $crypto->recordFunding($user, $asset, CryptoAmount::parse($d['amount'], $asset->decimals), $request->user(), $d['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->bad($e);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Funding recorded.']);
    }

    public function adjust(Request $request, User $user, CryptoService $crypto)
    {
        abort_if($user->isAdmin(), 404);
        Gate::authorize('staff.canAdjust');
        $d = $request->validate(['asset_id' => ['required', 'exists:crypto_assets,id'], 'direction' => ['required', 'in:credit,debit'], 'amount' => ['required', 'string', 'max:30'], 'description' => ['required', 'string', 'max:140'], 'reason_code' => ['required', 'exists:reason_codes,code']]);
        $asset = CryptoAsset::findOrFail($d['asset_id']);
        try {
            $units = CryptoAmount::parse($d['amount'], $asset->decimals);
            // Managers adjust up to the usual dollar limit. With no rate set, only a Super Admin may adjust.
            Gate::authorize('staff.adjustBalance', [$asset->usdCents($units) ?? PHP_INT_MAX]);
            $crypto->adjust($user, $asset, $d['direction'] === 'credit' ? $units : -$units, $d['description'], $d['reason_code'], $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->bad($e);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Wallet adjusted.']);
    }

    public function asset(Request $request, CryptoAsset $asset)
    {
        Gate::authorize('staff.manageAdmin');
        $d = $request->validate(['network_fee' => ['required', 'numeric', 'min:0'], 'usd_rate' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'enabled' => ['nullable', 'boolean'], 'live' => ['nullable', 'boolean']]);
        $old = $asset->only(['network_fee', 'usd_rate_cents', 'enabled', 'live_rate']);
        $live = (bool) ($d['live'] ?? false) && $asset->coingecko_id;
        $asset->update([
            'network_fee' => CryptoAmount::parse((string) $d['network_fee'], $asset->decimals),
            // A live asset keeps the fetched price; the typed rate only applies when live prices are off.
            'usd_rate_cents' => $live ? $asset->usd_rate_cents : (isset($d['usd_rate']) ? (int) round($d['usd_rate'] * 100) : null),
            'live_rate' => $live,
            'rate_updated_at' => $live ? $asset->rate_updated_at : now(),
            'enabled' => (bool) ($d['enabled'] ?? false),
        ]);
        Audit::record('crypto.asset_changed', $asset, $old, $asset->only(['network_fee', 'usd_rate_cents', 'enabled', 'live_rate']), $request->user()->id);

        return back()->with('toast', ['type' => 'ok', 'message' => $asset->symbol.' settings saved.']);
    }

    public function refreshRates(CryptoRates $rates)
    {
        Gate::authorize('staff.manageAdmin');
        $n = $rates->refresh();

        return back()->with('toast', $n > 0
            ? ['type' => 'ok', 'message' => $n.' price'.($n === 1 ? '' : 's').' updated from '.CryptoRates::SOURCE.'.']
            : ['type' => 'bad', 'message' => 'Could not reach '.CryptoRates::SOURCE.' right now. The last known prices are still in use.']);
    }
}
