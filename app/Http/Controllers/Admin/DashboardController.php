<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Transaction;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $open = Transaction::whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Held->value]);

        return view('admin.dashboard', [
            'pending' => Transaction::where('status', TransactionStatus::Pending->value)->count(),
            'held' => Transaction::where('status', TransactionStatus::Held->value)->count(),
            'overdue' => (clone $open)->where('created_at', '<=', now()->subWeekdays(3))->count(),
            'inquiries' => Inquiry::whereNull('handled_at')->count(),
            'members' => User::where('role', 'user')->count(),
            'oldest' => (clone $open)->with('fromAccount.product')->orderBy('created_at')->limit(6)->get(),
            'latestInquiries' => Inquiry::whereNull('handled_at')->latest('id')->limit(5)->get(),
        ]);
    }
}
