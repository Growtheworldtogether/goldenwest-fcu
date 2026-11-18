<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Audit;
use Illuminate\Http\Request;

class OverdraftController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $accounts = $u->accounts()->with(['product', 'overdraftAccount'])->where('accounts.status', 'active')->where('accounts.type', 'standard')->get();
        $checking = $accounts->filter(fn ($a) => $a->product->kind === 'share_draft');

        return view('portal.overdraft', [
            'checking' => $checking,
            'sources' => $accounts->filter(fn ($a) => in_array($a->product->kind, ['share_savings', 'money_market'], true)),
            'isBusiness' => $checking->mapWithKeys(fn ($a) => [$a->id => $a->product->isBusiness()]),
            'fee' => \App\Support\Money::cents((int) config('bank.overdraft_transfer_fee')),
            'recent' => Transaction::with(['fromAccount', 'toAccount'])->where('type', TransactionType::OverdraftTransfer->value)->whereIn('to_account_id', $checking->pluck('id'))->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, \App\Models\Account $account)
    {
        $u = $request->user();
        abort_unless($u->accounts()->where('accounts.id', $account->id)->where('accounts.type', 'standard')->exists(), 404);
        $id = $request->filled('source_id') ? (int) $request->input('source_id') : null;
        if ($id !== null) {
            $ok = $u->accounts()->whereHas('product', fn ($q) => $q->whereIn('kind', ['share_savings', 'money_market']))->where('accounts.id', $id)->where('accounts.status', 'active')->where('accounts.type', 'standard')->where('accounts.id', '!=', $account->id)->exists();
            $source = $ok ? $u->accounts()->with('product')->where('accounts.id', $id)->first() : null;
            if ($source && $source->product->isBusiness() !== $account->load('product')->product->isBusiness()) {
                $ok = false;
            }
            if (! $ok) {
                return back()->with('toast', ['type' => 'bad', 'message' => 'Choose one of your savings or money market accounts.']);
            }
        }
        $old = $account->overdraft_account_id;
        $account->update(['overdraft_account_id' => $id]);
        Audit::record('overdraft.changed', $account, ['source' => $old], ['source' => $id], $u->id);

        return back()->with('toast', ['type' => 'ok', 'message' => $id ? 'Overdraft protection is on.' : 'Overdraft protection is off.']);
    }
}
