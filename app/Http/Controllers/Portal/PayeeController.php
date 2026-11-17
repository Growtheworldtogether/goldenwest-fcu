<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payee;
use App\Services\Ledger\TransferException;
use App\Services\PayeeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayeeController extends Controller
{
    use Concerns\ChecksStaffCode;

    public function index(Request $request)
    {
        return view('portal.payees', ['payees' => Payee::where('user_id', $request->user()->id)->where('status', 'active')->orderBy('name')->get(), 'hours' => (int) config('bank.payee_cooling_hours')]);
    }

    public function store(Request $request, PayeeService $payees)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'routing_number' => ['required', 'digits:9'],
            'account_number' => ['required', 'digits_between:4,17', 'confirmed'],
            'account_type' => ['required', 'in:checking,savings'],
        ], ['account_number.confirmed' => 'The account numbers do not match.', 'routing_number.digits' => 'A routing number has nine digits.']);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        $payee = $payees->add($request->user(), $data);

        return redirect()->route('payees.index')->with('status', $payee->label().' is saved. For your protection it can be used after '.$payee->activates_at->timezone('America/Los_Angeles')->format('M j \a\t g:i a T').'.');
    }

    public function destroy(Request $request, Payee $payee, PayeeService $payees)
    {
        abort_unless($payee->user_id === $request->user()->id && $payee->status === 'active', 404);
        $payees->remove($payee, $request->user());

        return redirect()->route('payees.index')->with('status', $payee->label().' was removed.');
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('portal.send-external', [
            'accounts' => $user->accounts()->with('product')->where('accounts.status', 'active')->where('accounts.type', 'standard')->orderBy('accounts.id')->get(),
            'payees' => Payee::where('user_id', $user->id)->where('status', 'active')->orderBy('name')->get(),
            'token' => (string) Str::ulid(),
        ]);
    }

    public function send(Request $request, PayeeService $payees)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'payee_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
            'memo' => ['nullable', 'string', 'max:140'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ]);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        $account = $request->user()->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();
        Gate::authorize('transfer', $account);
        $payee = Payee::where('user_id', $request->user()->id)->findOrFail($data['payee_id']);

        try {
            $amount = Money::parse($data['amount']);
            $tx = $payees->send($request->user(), $account, $payee, $amount, $data['memo'] ?? null, $data['token'], $request->session()->get('impersonation.admin_id'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 250.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('transactions.show', $tx->reference)->with('status', $tx->status->value === 'completed'
            ? 'Your transfer of '.$amount->format().' to '.$payee->label().' is on its way. It arrives the next business day.'
            : 'Your transfer of '.$amount->format().' is being reviewed. The money is set aside and we will update you shortly.');
    }
}
