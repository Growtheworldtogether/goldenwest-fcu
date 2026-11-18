<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CryptoAddress;
use App\Models\CryptoAsset;
use App\Models\CryptoTransaction;
use App\Models\CryptoWallet;
use App\Services\CryptoRates;
use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CryptoController extends Controller
{
    use Concerns\ChecksStaffCode;

    private function assets()
    {
        app(CryptoRates::class)->refreshIfStale();

        return CryptoAsset::where('enabled', true)->orderBy('id')->get();
    }

    private function pick(Request $request): CryptoAsset
    {
        $assets = $this->assets();
        abort_if($assets->isEmpty(), 404);

        return $assets->firstWhere('id', (int) $request->input('asset', $request->query('asset'))) ?? $assets->first();
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $wallets = CryptoWallet::with('asset')->where('user_id', $u->id)->get()->keyBy('asset_id');
        $assets = $this->assets();
        $estimate = $assets->sum(fn ($a) => $a->usdCents($wallets->get($a->id)?->balance ?? 0) ?? 0);

        return view('portal.crypto', [
            'assets' => $assets, 'wallets' => $wallets, 'estimate' => $estimate,
            'history' => CryptoTransaction::with('asset')->where('user_id', $u->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function fund(Request $request, CryptoService $crypto)
    {
        $u = $request->user();
        $asset = $this->pick($request);
        $address = $crypto->activeAddress($u, $asset);

        return view('portal.crypto-fund', [
            'assets' => $this->assets(), 'asset' => $asset, 'address' => $address,
            'requested' => ! $address && CryptoAddress::where('user_id', $u->id)->where('asset_id', $asset->id)->where('status', 'requested')->exists(),
            'waiting' => CryptoTransaction::where('user_id', $u->id)->where('asset_id', $asset->id)->where('type', 'fund')->where('status', 'pending')->latest('id')->get(),
            'token' => (string) Str::ulid(),
        ]);
    }

    public function requestAddress(Request $request, CryptoService $crypto)
    {
        $asset = $this->pick($request);
        try {
            $crypto->requestAddress($request->user(), $asset);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return redirect()->route('crypto.fund', ['asset' => $asset->id])->with('toast', ['type' => 'ok', 'message' => 'We have your request. A team member will add your '.$asset->symbol.' deposit address soon.']);
    }

    public function declare(Request $request, CryptoService $crypto)
    {
        $asset = $this->pick($request);
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:140'], 'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/']]);
        try {
            $units = $asset->unitsFromInput($data['amount']);
            $funding = $crypto->declareFunding($request->user(), $asset, $units, $data['note'] ?? null, $data['token'], $request->session()->get('impersonation.admin_id'));
            if ($admin = \App\Services\StaffDirectory::actingSuperAdmin()) {
                $crypto->confirmFunding($funding, $admin);

                return redirect()->route('crypto.fund', ['asset' => $asset->id])->with('toast', ['type' => 'ok', 'message' => 'Deposit confirmed and added to the wallet.']);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('crypto.fund', ['asset' => $asset->id])->with('toast', ['type' => 'ok', 'message' => 'Thanks. We will add the funds to your wallet once we confirm the deposit.']);
    }

    public function create(Request $request)
    {
        return view('portal.crypto-send', [
            'assets' => $this->assets(), 'asset' => $this->pick($request),
            'wallets' => CryptoWallet::where('user_id', $request->user()->id)->get()->keyBy('asset_id'),
            'token' => (string) Str::ulid(),
        ]);
    }

    public function store(Request $request, CryptoService $crypto)
    {
        $asset = $this->pick($request);
        $data = $request->validate([
            'address' => ['required', 'string', 'max:120'], 'memo' => ['nullable', 'string', 'max:60'], 'amount' => ['required', 'string', 'max:30'],
            'understand' => ['accepted'], 'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], ['understand.accepted' => 'Please confirm that you understand a crypto send cannot be taken back.']);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        // Sending crypto asks for the member's password again, like other sensitive changes.
        if (! $request->session()->has('impersonation')) {
            $request->validate(['password' => ['required', 'string']], ['password.required' => 'Enter your password to confirm this send.']);
            if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), $request->user()->password)) {
                return back()->withInput($request->except('password'))->withErrors(['password' => 'That password is not correct.']);
            }
        }

        try {
            $units = $asset->unitsFromInput($data['amount']);
            $tx = $crypto->send($request->user(), $asset, $data['address'], $data['memo'] ?? null, $units, $data['token'], $request->session()->get('impersonation.admin_id'));
            if ($admin = \App\Services\StaffDirectory::actingSuperAdmin()) {
                $crypto->decideSend($tx, $admin, 'approve');

                return redirect()->route('crypto.index')->with('toast', ['type' => 'ok', 'message' => 'Send approved: '.$asset->usd($tx->amount).' in '.$asset->symbol.'.']);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput($request->except(['password', 'admin_code']))->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('crypto.index')->with('toast', ['type' => 'ok', 'message' => 'Send requested: '.$asset->usd($tx->amount).' in '.$asset->symbol.'. A team member will review it. The amount is set aside until then.']);
    }

    public function cancel(Request $request, CryptoTransaction $tx, CryptoService $crypto)
    {
        abort_unless($tx->user_id === $request->user()->id, 404);
        try {
            $crypto->cancel($tx, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Send cancelled. The amount is available again.']);
    }
}
