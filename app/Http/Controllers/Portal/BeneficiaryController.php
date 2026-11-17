<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\BeneficiaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BeneficiaryController extends Controller
{
    public function index(Request $request, BeneficiaryService $svc)
    {
        $accounts = $request->user()->accounts()->with('product')->where('accounts.type', 'standard')->orderBy('accounts.id')->get();

        return view('portal.beneficiaries', [
            'accounts' => $accounts,
            'lists' => $accounts->mapWithKeys(fn ($a) => [$a->id => $svc->forAccount($a)]),
        ]);
    }

    public function edit(Request $request, Account $account, BeneficiaryService $svc)
    {
        Gate::authorize('view', $account);
        abort_unless($account->type === 'standard', 404);

        return view('portal.beneficiaries-edit', ['account' => $account->load('product'), 'current' => $svc->forAccount($account)]);
    }

    public function update(Request $request, Account $account, BeneficiaryService $svc)
    {
        Gate::authorize('view', $account);
        abort_unless($account->type === 'standard', 404);
        $request->validate(['rows' => ['nullable', 'array', 'max:12']]);

        $saved = $svc->replace($account, $request->user(), $request->input('rows', []));

        return redirect()->route('beneficiaries.index')->with('toast', ['type' => 'ok', 'message' => $saved->isEmpty() ? 'Beneficiaries cleared for '.$account->nickname.'.' : 'Beneficiaries saved for '.$account->nickname.'.']);
    }
}
