<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BillPayment;
use App\Models\Biller;
use App\Services\BillPayService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BillController extends Controller
{
    public function index(Request $request, BillPayService $bills)
    {
        $u = $request->user();
        $bills->refresh($u->id);

        return view('portal.bills', [
            'billers' => Biller::where('user_id', $u->id)->where('status', 'active')->orderBy('name')->get(),
            'upcoming' => BillPayment::with(['biller', 'account'])->where('user_id', $u->id)->where('status', 'scheduled')->orderBy('send_date')->get(),
            'history' => BillPayment::with('biller')->where('user_id', $u->id)->where('status', '!=', 'scheduled')->latest('id')->limit(15)->get(),
            'accounts' => $u->accounts()->with('product')->where('accounts.status', 'active')->where('accounts.type', 'standard')->get(),
            'review' => Money::cents((int) config('bank.bill_review_threshold')),
        ]);
    }

    public function storeBiller(Request $request, BillPayService $bills)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'category' => ['required', Rule::in(array_keys(config('spending.biller_categories')))],
            'account_reference' => ['required', 'string', 'max:40'],
        ], ['name.required' => 'Enter the name of the company.', 'account_reference.required' => 'Enter your account number with them.']);
        $bills->addBiller($request->user(), $d['name'], $d['category'], $d['account_reference']);

        return back()->with('toast', ['type' => 'ok', 'message' => $d['name'].' saved.']);
    }

    public function destroyBiller(Request $request, Biller $biller, BillPayService $bills)
    {
        $bills->removeBiller($biller, $request->user());

        return back()->with('toast', ['type' => 'info', 'message' => 'Biller removed. Any scheduled payments to them were cancelled.']);
    }

    public function store(Request $request, BillPayService $bills)
    {
        $d = $request->validate([
            'biller_id' => ['required', 'integer'], 'account_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'min:1'],
            'send_date' => ['required', 'date'], 'recurrence' => ['required', 'in:none,weekly,monthly'],
        ], ['amount.required' => 'Enter an amount.', 'send_date.required' => 'Pick a send date.']);
        $biller = Biller::where('id', $d['biller_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $from = $request->user()->accounts()->where('accounts.id', $d['account_id'])->firstOrFail();

        $p = $bills->schedule($request->user(), $biller, $from, Money::cents((int) round($d['amount'] * 100)), Carbon::parse($d['send_date']), $d['recurrence']);

        return back()->with('toast', ['type' => 'ok', 'message' => $p->status === 'scheduled' ? 'Payment scheduled for '.$p->send_date->format('M j').'.' : ($p->status === 'review' ? 'Payment sent for review. We will release it once approved.' : ($p->status === 'failed' ? 'We could not send this payment: '.$p->failure : 'Payment sent. Expected by '.$p->estimated_delivery->format('M j').'.'))]);
    }

    public function cancel(Request $request, BillPayment $payment, BillPayService $bills)
    {
        try {
            $bills->cancel($payment, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'info', 'message' => 'Payment cancelled.']);
    }
}
