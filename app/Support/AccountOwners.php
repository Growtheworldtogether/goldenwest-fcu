<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Looks up who holds each account in a set of transactions, for staff screens. */
final class AccountOwners
{
    /** @return Collection<int, User|null> account id => holder */
    public static function for(iterable $transactions): Collection
    {
        $tx = $transactions instanceof \Illuminate\Pagination\AbstractPaginator ? $transactions->getCollection() : collect($transactions);
        $ids = $tx->pluck('from_account_id')->merge($tx->pluck('to_account_id'))->filter()->unique();
        $holders = DB::table('account_holders')->whereIn('account_id', $ids)->where('status', 'active')->orderBy('role')->get();
        $users = User::whereIn('id', $holders->pluck('user_id'))->get()->keyBy('id');

        return $holders->mapWithKeys(fn ($h) => [$h->account_id => $users[$h->user_id] ?? null]);
    }
}
