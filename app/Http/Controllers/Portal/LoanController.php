<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\Ledger\TransferException;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class LoanController extends Controller
{
    private function loan(Request $request, Loan $loan): Loan
    {
        abort_unless($loan->user_id === $request->user()->id, 404);

        return $loan;
    }

    private function application(Request $request, LoanApplication $app): LoanApplication
    {
        abort_unless($app->user_id === $request->user()->id, 404);

        return $app;
    }

    private function fail(string $message)
    {
        return back()->with('toast', ['type' => 'bad', 'message' => $message]);
    }

    public function index(Request $request)
    {
        $id = $request->user()->id;

        return view('portal.loans', [
            'loans' => Loan::with('product')->where('user_id', $id)->latest('id')->get(),
            'applications' => LoanApplication::with('product')->where('user_id', $id)->whereIn('status', ['submitted', 'in_review', 'needs_info', 'rejected'])->latest('id')->limit(5)->get(),
            'products' => LoanProduct::where('active', true)->get(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('portal.loan-apply', [
            'products' => LoanProduct::where('active', true)->get(),
            'selected' => $request->query('type'),
            'accounts' => $user->accounts()->where('accounts.status', 'active')->where('accounts.type', 'standard')->get(),
        ]);
    }

    public function store(Request $request, LoanService $loans)
    {
        $data = $request->validate([
            'product' => ['required', Rule::exists('loan_products', 'code')],
            'amount' => ['required', 'numeric', 'min:1'],
            'term_months' => ['required', 'integer'],
            'purpose' => ['required', 'string', 'max:120'],
            'annual_income' => ['required', 'numeric', 'min:0'],
            'employment' => ['required', Rule::in(['employed', 'self_employed', 'retired', 'other'])],
            'deposit_account_id' => ['required', 'integer'],
            'vehicle_year' => ['nullable', 'digits:4'], 'vehicle_make' => ['nullable', 'string', 'max:40'], 'vehicle_model' => ['nullable', 'string', 'max:40'],
            'vehicle_value' => ['nullable', 'numeric', 'min:0'],
        ], ['purpose.required' => 'Tell us what the loan is for.', 'amount.required' => 'Enter the amount you would like to borrow.']);

        $product = LoanProduct::where('code', $data['product'])->firstOrFail();
        if ($product->type === 'auto') {
            $request->validate(['vehicle_year' => ['required'], 'vehicle_make' => ['required'], 'vehicle_model' => ['required'], 'vehicle_value' => ['required', 'numeric']], ['vehicle_year.required' => 'Enter the vehicle year.', 'vehicle_make.required' => 'Enter the vehicle make.', 'vehicle_model.required' => 'Enter the vehicle model.', 'vehicle_value.required' => 'Enter the vehicle price.']);
        }
        $cents = fn ($v) => $v === null || $v === '' ? null : (int) round(((float) $v) * 100);

        $app = $loans->apply($request->user(), $product, [
            'amount' => $cents($data['amount']), 'term_months' => (int) $data['term_months'], 'purpose' => $data['purpose'], 'annual_income' => $cents($data['annual_income']),
            'employment' => $data['employment'], 'deposit_account_id' => (int) $data['deposit_account_id'],
            'vehicle_year' => $data['vehicle_year'] ?? null, 'vehicle_make' => $data['vehicle_make'] ?? null, 'vehicle_model' => $data['vehicle_model'] ?? null, 'vehicle_value' => $cents($data['vehicle_value'] ?? null),
        ]);

        return redirect()->route('borrow.application', $app->reference)->with('toast', ['type' => 'ok', 'message' => 'Application '.$app->reference.' received. We will review it and update you here.']);
    }

    public function application_show(Request $request, LoanApplication $application)
    {
        $this->application($request, $application);

        return view('portal.loan-application', ['app' => $application->load(['product', 'depositAccount', 'loan', 'decisions']), 'reasons' => \App\Models\ReasonCode::pluck('label', 'code')]);
    }

    public function reply(Request $request, LoanApplication $application, LoanService $loans)
    {
        $text = $request->validate(['reply' => ['required', 'string', 'max:2000']], ['reply.required' => 'Write your answer first.'])['reply'];
        try {
            $loans->reply($this->application($request, $application), $request->user(), $text);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Thanks, we have your answer.']);
    }

    public function withdraw(Request $request, LoanApplication $application, LoanService $loans)
    {
        try {
            $loans->withdraw($this->application($request, $application), $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return redirect()->route('borrow.index')->with('toast', ['type' => 'info', 'message' => 'Application withdrawn.']);
    }

    public function show(Request $request, Loan $loan, LoanService $loans)
    {
        $this->loan($request, $loan);
        $user = $request->user();

        return view('portal.loan', [
            'loan' => $loan->load(['product', 'schedule', 'autopayAccount', 'account']),
            'payoff' => $loan->isOpen() ? $loans->payoffAmount($loan) : null,
            'accounts' => $user->accounts()->where('accounts.status', 'active')->where('accounts.type', 'standard')->get(),
        ]);
    }

    public function pay(Request $request, Loan $loan, LoanService $loans)
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'kind' => ['required', 'in:next,extra,payoff'], 'amount' => ['nullable', 'numeric', 'min:0.01']]);
        $this->loan($request, $loan);
        $from = $request->user()->accounts()->where('accounts.id', $data['account_id'])->where('accounts.status', 'active')->where('accounts.type', 'standard')->first();
        if (! $from) {
            return $this->fail('Choose one of your accounts to pay from.');
        }

        try {
            match ($data['kind']) {
                'next' => $loans->payNext($loan, $from),
                'extra' => $loans->payExtra($loan, $from, Money::cents((int) round(((float) ($data['amount'] ?? 0)) * 100))),
                'payoff' => $loans->payOff($loan, $from),
            };
        } catch (TransferException|InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $data['kind'] === 'payoff' ? 'Your loan is paid off.' : 'Payment received. Thank you.']);
    }

    public function autopay(Request $request, Loan $loan, LoanService $loans)
    {
        $id = $request->boolean('on') ? (int) $request->input('account_id') : null;
        try {
            $loans->setAutopay($this->loan($request, $loan), $request->user(), $id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $id ? 'Autopay is on.' : 'Autopay is off.']);
    }
}
