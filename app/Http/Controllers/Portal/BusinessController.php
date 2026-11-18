<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\BusinessService;
use App\Support\BusinessRules;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function show(Request $request, BusinessService $business)
    {
        $user = $request->user();
        $profile = $user->businessProfile;

        return view('portal.business', [
            'profile' => $profile,
            'verified' => $user->kyc_status === 'verified',
            'missing' => $profile && ! $profile->isPending() ? $business->missing($user) : [],
            'accounts' => $profile ? $user->accounts()->with('product')->whereHas('product', fn ($q) => $q->where('segment', 'business'))->get() : collect(),
        ]);
    }

    public function store(Request $request, BusinessService $business)
    {
        $data = $request->validate(BusinessRules::rules('required'), BusinessRules::messages('required'));
        $business->request($request->user(), $data, $request->file('business_document'));

        return redirect()->route('business.show')->with('toast', ['type' => 'ok', 'message' => 'Thank you. We received your business document and will review it within one business day.']);
    }

    public function open(Request $request, BusinessService $business)
    {
        $code = (string) $request->validate(['product' => ['required', 'string']])['product'];
        $account = $business->openMore($request->user(), $code);

        return redirect()->route('accounts.show', $account)->with('status', 'Your new account is open. There was no fee and no deposit required.');
    }
}
