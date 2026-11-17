<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\WireRecipient;
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
            'wires' => WireTransfer::with('transaction')->where('user_id', $request->user()->id)->latest('id')->limit(30)->get(),
            'recipients' => WireRecipient::where('user_id', $request->user()->id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('portal.wire-new', [
            'accounts' => $user->accounts()->with('product')->where('accounts.status', 'active')->where('accounts.type', 'standard')->orderBy('accounts.id')->get(),
            'recipients' => WireRecipient::where('user_id', $user->id)->where('status', 'active')->orderBy('name')->get(),
            'token' => (string) Str::ulid(),
            'fee' => WireService::fee(),
            'cutoff' => WireService::cutoff(),
            'processOn' => WireService::processOn(),
            'verified' => $user->kyc_status === 'verified',
        ]);
    }

    public function store(Request $request, WireService $wires)
    {
        $saved = $request->input('recipient_id');
        $useSaved = $saved !== null && $saved !== '' && $saved !== 'new';
        $newRule = $useSaved ? ['nullable'] : ['required'];

        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'recipient_id' => ['nullable', 'string'],
            'recipient_name' => [...$newRule, 'string', 'max:120'],
            'recipient_address' => [...$newRule, 'string', 'min:8', 'max:200'],
            'bank_name' => [...$newRule, 'string', 'max:120'],
            'routing_number' => [...$newRule, 'digits:9'],
            'account_number' => [...$newRule, 'digits_between:4,17', 'confirmed'],
            'amount' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'string', 'max:140'],
            'message' => ['nullable', 'string', 'max:140'],
            'save_recipient' => ['nullable', 'boolean'],
            'understand' => ['accepted'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], [
            'understand.accepted' => 'Please confirm that you understand wires cannot be taken back.',
            'recipient_name.required' => 'Enter the recipient\'s full name.',
            'recipient_address.required' => 'Enter the recipient\'s address.',
            'bank_name.required' => 'Enter the name of the recipient\'s bank.',
            'routing_number.required' => 'Enter the bank\'s routing number.',
            'routing_number.digits' => 'A routing number has nine digits.',
            'account_number.required' => 'Enter the recipient\'s account number.',
            'account_number.confirmed' => 'The account numbers do not match.',
            'purpose.required' => 'Tell us what the wire is for.',
        ]);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        $account = $request->user()->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();
        Gate::authorize('transfer', $account);

        if ($useSaved) {
            $r = WireRecipient::where('user_id', $request->user()->id)->where('status', 'active')->findOrFail($saved);
            $to = ['name' => $r->name, 'address' => $r->address, 'bank_name' => $r->bank_name, 'routing_number' => $r->routing_number, 'account_number' => $r->account_number];
        } else {
            $to = ['name' => $data['recipient_name'], 'address' => $data['recipient_address'], 'bank_name' => $data['bank_name'], 'routing_number' => $data['routing_number'], 'account_number' => $data['account_number']];
        }

        try {
            $amount = Money::parse($data['amount']);
            $tx = $wires->send($request->user(), $account, $to, $amount, $data['purpose'], $data['message'] ?? null, $data['token'], ! $useSaved && $request->boolean('save_recipient'), $request->session()->get('impersonation.admin_id'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 5000.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('transactions.show', $tx->reference)->with('status', 'Your wire request for '.$amount->format().' was received. Every wire is reviewed first. The amount and the '.WireService::fee()->format().' fee are set aside now, and we will update you here.');
    }

    public function destroy(Request $request, WireRecipient $recipient, WireService $wires)
    {
        abort_unless($recipient->user_id === $request->user()->id && $recipient->status === 'active', 404);
        $wires->forget($recipient, $request->user());

        return redirect()->route('wires.index')->with('status', $recipient->label().' was removed.');
    }
}
