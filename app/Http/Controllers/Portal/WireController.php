<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payee;
use App\Models\WireTransfer;
use App\Services\Ledger\TransferException;
use App\Services\WireService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WireController extends Controller
{
    use Concerns\ChecksStaffCode;

    public function index(Request $request)
    {
        return view('portal.wires', [
            'wires' => WireTransfer::with(['transaction', 'payee'])->where('user_id', $request->user()->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('portal.wire-new', [
            'accounts' => $user->accounts()->with('product')->where('accounts.status', 'active')->orderBy('accounts.id')->get(),
            'payees' => Payee::where('user_id', $user->id)->where('status', 'active')->orderBy('name')->get(),
            'token' => (string) Str::ulid(),
            'fee' => WireService::fee(),
            'cutoff' => WireService::cutoff(),
            'processOn' => WireService::processOn(),
            'verified' => $user->kyc_status === 'verified',
        ]);
    }

    public function store(Request $request, WireService $wires)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'payee_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
            'recipient_address' => ['required', 'string', 'min:8', 'max:200'],
            'purpose' => ['required', 'string', 'max:140'],
            'understand' => ['accepted'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], ['understand.accepted' => 'Please confirm that you understand wires cannot be taken back.', 'recipient_address.required' => 'Enter the recipient\'s address.', 'purpose.required' => 'Tell us what the wire is for.']);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        $account = $request->user()->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();
        Gate::authorize('transfer', $account);
        $payee = Payee::where('user_id', $request->user()->id)->findOrFail($data['payee_id']);

        try {
            $amount = Money::parse($data['amount']);
            $tx = $wires->send($request->user(), $account, $payee, $amount, $data['recipient_address'], $data['purpose'], $data['token'], $request->session()->get('impersonation.admin_id'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 5000.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('transactions.show', $tx->reference)->with('status', 'Your wire request for '.$amount->format().' was received. Every wire is reviewed first. The amount and the '.WireService::fee()->format().' fee are set aside now, and we will update you here.');
    }
}
