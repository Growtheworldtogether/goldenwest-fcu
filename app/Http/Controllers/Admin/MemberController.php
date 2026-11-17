<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Models\ReasonCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.lookupMembers');
        $q = trim((string) $request->query('q'));

        $members = User::where('role', 'user')
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($q, $digits) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('member_number', 'like', "{$q}%");
                    if (strlen($digits) >= 4) {
                        $w->orWhere('phone', 'like', '%'.$digits.'%')
                            ->orWhereHas('accounts', fn ($a) => $a->where('account_number', 'like', "%{$digits}"));
                    }
                });
            })
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.members', ['members' => $members, 'q' => $q]);
    }

    public function show(User $user)
    {
        Gate::authorize('staff.lookupMembers');
        abort_if($user->isAdmin(), 404);

        $accounts = $user->accounts()->with('product')->orderBy('accounts.id')->get();
        $ids = $accounts->pluck('id');

        return view('admin.member', [
            'member' => $user->load('profile'),
            'accounts' => $accounts,
            'restrictions' => AccountRestriction::active()->with(['account', 'user'])
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('account_id', $ids))->latest('id')->get(),
            'transactions' => Transaction::with(['fromAccount', 'toAccount'])
                ->where(fn ($q) => $q->whereIn('from_account_id', $ids)->orWhereIn('to_account_id', $ids))->latest('id')->limit(12)->get(),
            'waiting' => Transaction::whereIn('from_account_id', $ids)->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Held->value])->latest('id')->get(),
            'audit' => AuditLog::where(fn ($q) => $q->where('actor_id', $user->id)->orWhere(fn ($s) => $s->where('subject_type', 'User')->where('subject_id', $user->id)))->latest('id')->limit(12)->get(),
            'sessions' => Impersonation::with('admin')->where('member_id', $user->id)->latest('id')->limit(5)->get(),
            'lockReasons' => ReasonCode::whereIn('applies_to', ['restriction', 'all'])->orderBy('label')->pluck('label', 'code'),
            'adjustReasons' => ReasonCode::whereIn('applies_to', ['adjustment', 'all'])->orderBy('label')->pluck('label', 'code'),
            'actingReasons' => ReasonCode::where('applies_to', 'impersonation')->orderBy('label')->pluck('label', 'code'),
            'cards' => \App\Models\Card::where('user_id', $user->id)->latest('id')->get(),
            'payees' => \App\Models\Payee::where('user_id', $user->id)->where('status', 'active')->orderBy('name')->get(),
            'kycDocs' => \App\Models\KycDocument::where('user_id', $user->id)->where('status', '!=', 'replaced')->latest('id')->get(),
            'features' => \App\Models\UserFeature::where('user_id', $user->id)->whereNull('disabled_at')->pluck('feature')->all(),
            'token' => (string) Str::ulid(),
        ]);
    }
}
