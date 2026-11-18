<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** The page a new member sees right after joining: a short check, then their new account details. */
class WelcomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if ((int) $request->session()->get('welcome_until', 0) < now()->timestamp) {
            return redirect()->route('dashboard');
        }
        $user = $request->user();

        return view('portal.welcome', [
            'user' => $user,
            'accounts' => $user->accounts()->with('product')->orderBy('accounts.id')->get(),
            'docs' => \App\Models\KycDocument::where('user_id', $user->id)->where('status', '!=', 'replaced')->count(),
        ]);
    }
}
