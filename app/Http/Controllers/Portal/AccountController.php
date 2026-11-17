<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\AccountOpening;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    private const OPENABLE = [
        'GOLDEN-SAVINGS' => 'Golden Savings',
        'EVERYDAY-CHECKING' => 'Everyday Checking',
        'MONEY-MARKET' => 'Money Market',
    ];

    public function create()
    {
        return view('portal.open-account', ['products' => self::OPENABLE]);
    }

    public function store(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'product' => ['required', Rule::in(array_keys(self::OPENABLE))],
            'nickname' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        if ($user->accounts()->where('accounts.status', 'active')->where('accounts.type', 'standard')->count() >= 8) {
            return back()->withErrors(['product' => 'You have reached the limit of eight accounts. Please contact Customer Care to open more.']);
        }

        $overdraft = $data['product'] === 'EVERYDAY-CHECKING'
            ? $user->accounts()->whereHas('product', fn ($q) => $q->where('kind', 'share_savings'))->where('accounts.status', 'active')->orderBy('accounts.id')->value('accounts.id')
            : null;

        $account = $opening->open($user, $data['product'], $data['nickname'] ?? null, $overdraft);

        return redirect()->route('accounts.show', $account)->with('status', 'Your new account is open. There was no fee and no deposit required.');
    }
}
