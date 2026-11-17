<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Payee;
use App\Services\DepositService;
use App\Services\Ledger\TransferException;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DepositController extends Controller
{
    use Concerns\ChecksStaffCode;

    public function index(Request $request)
    {
        $deposits = Deposit::with(['transaction', 'account', 'payee'])->where('user_id', $request->user()->id)->latest('id')->limit(30)->get();

        return view('portal.deposits', ['deposits' => $deposits, 'verified' => $request->user()->kyc_status === 'verified']);
    }

    public function createCheck(Request $request)
    {
        return view('portal.deposit-check', [
            'accounts' => $request->user()->accounts()->with('product')->where('accounts.status', 'active')->orderBy('accounts.id')->get(),
            'token' => (string) Str::ulid(),
            'verified' => $request->user()->kyc_status === 'verified',
            'limit' => Money::cents(config('bank.check_deposit_limit')),
        ]);
    }

    public function storeCheck(Request $request, DepositService $deposits)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
            'check_number' => ['nullable', 'string', 'regex:/^\d{1,12}$/'],
            'front' => ['required', 'file'],
            'back' => ['required', 'file'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], ['check_number.regex' => 'The check number is digits only.', 'front.required' => 'Add a photo of the front of the check.', 'back.required' => 'Add a photo of the back of the check.']);

        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }
        $account = $request->user()->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();
        Gate::authorize('transfer', $account);

        try {
            $amount = Money::parse($data['amount']);
            $deposit = $deposits->check($request->user(), $account, $amount, $data['check_number'] ?? null, $request->file('front'), $request->file('back'), $data['token'], $request->session()->get('impersonation.admin_id'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 250.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('deposits.index')->with('status', 'We received your check for '.$amount->format().'. A person reviews every check deposit, and the money shows in your account once it is approved.');
    }

    public function createAch(Request $request)
    {
        $user = $request->user();

        return view('portal.deposit-ach', [
            'accounts' => $user->accounts()->with('product')->where('accounts.status', 'active')->orderBy('accounts.id')->get(),
            'payees' => Payee::where('user_id', $user->id)->where('status', 'active')->orderBy('name')->get(),
            'token' => (string) Str::ulid(),
            'verified' => $user->kyc_status === 'verified',
            'limit' => Money::cents(config('bank.ach_pull_limit')),
        ]);
    }

    public function storeAch(Request $request, DepositService $deposits)
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'payee_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
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
            $deposits->pull($request->user(), $account, $payee, $amount, $data['token'], $request->session()->get('impersonation.admin_id'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 250.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('deposits.index')->with('status', 'We requested '.$amount->format().' from '.$payee->label().'. It is reviewed first and arrives on the next business day once approved.');
    }
}
