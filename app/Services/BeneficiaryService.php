<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Who receives an account if its holder dies. Each tier that has people must total exactly 100%. */
class BeneficiaryService
{
    public const RELATIONSHIPS = [
        'spouse' => 'Spouse or partner', 'child' => 'Child', 'parent' => 'Parent', 'sibling' => 'Brother or sister',
        'grandchild' => 'Grandchild', 'other_family' => 'Other family member', 'friend' => 'Friend', 'trust' => 'Trust or estate', 'other' => 'Other',
    ];

    public const TIERS = ['primary' => 'Primary', 'contingent' => 'Contingent'];

    public const MAX_PER_TIER = 6;

    public function forAccount(Account $account)
    {
        return Beneficiary::where('account_id', $account->id)->where('status', 'active')->orderByRaw("tier = 'contingent'")->orderBy('id')->get();
    }

    /** none, or complete. Rows can only be saved when complete, so there is no half-finished state. */
    public function state(Account $account): string
    {
        return Beneficiary::where('account_id', $account->id)->where('status', 'active')->exists() ? 'complete' : 'none';
    }

    /**
     * Replace the whole set for an account. Old rows stay as history. Returns the saved rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replace(Account $account, User $by, array $rows)
    {
        $rows = array_values(array_filter($rows, fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== ''));

        $clean = [];
        foreach ($rows as $i => $r) {
            $v = Validator::make($r, [
                'name' => ['required', 'string', 'max:120'],
                'relationship' => ['required', Rule::in(array_keys(self::RELATIONSHIPS))],
                'dob' => ['required', 'date', 'before:today', 'after:1900-01-01'],
                'share_percent' => ['required', 'integer', 'min:1', 'max:100'],
                'tier' => ['required', Rule::in(array_keys(self::TIERS))],
            ], [
                'name.required' => 'Enter a name.', 'relationship.required' => 'Choose a relationship.', 'dob.required' => 'Enter a birth date.',
                'dob.before' => 'The birth date must be in the past.', 'dob.after' => 'Check the birth year.',
                'share_percent.required' => 'Enter a share.', 'share_percent.integer' => 'The share must be a whole number.', 'share_percent.min' => 'The share must be at least 1%.', 'share_percent.max' => 'The share cannot be over 100%.',
            ]);
            if ($v->fails()) {
                throw ValidationException::withMessages(["rows.{$i}" => 'Person '.($i + 1).': '.$v->errors()->first()]);
            }
            $clean[] = $v->validated();
        }

        $byTier = collect($clean)->groupBy('tier');
        if ($clean && ! $byTier->has('primary')) {
            throw ValidationException::withMessages(['rows' => 'Add at least one primary beneficiary.']);
        }
        foreach (self::TIERS as $tier => $label) {
            $list = $byTier->get($tier, collect());
            if ($list->count() > self::MAX_PER_TIER) {
                throw ValidationException::withMessages(['rows' => "You can list up to ".self::MAX_PER_TIER." {$label} beneficiaries."]);
            }
            if ($list->isNotEmpty() && $list->sum('share_percent') !== 100) {
                throw ValidationException::withMessages(['rows' => "{$label} shares add up to {$list->sum('share_percent')}%. They must add up to exactly 100%."]);
            }
        }
        if (collect($clean)->map(fn ($r) => mb_strtolower(trim($r['name'])).'|'.$r['dob'].'|'.$r['tier'])->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => 'The same person is listed twice in one tier.']);
        }

        return DB::transaction(function () use ($account, $by, $clean) {
            $old = $this->forAccount($account);
            Beneficiary::where('account_id', $account->id)->where('status', 'active')->update(['status' => 'removed']);
            $new = collect($clean)->map(fn ($r) => Beneficiary::create([
                'account_id' => $account->id, 'name' => trim($r['name']), 'relationship' => $r['relationship'], 'dob' => $r['dob'],
                'share_percent' => (int) $r['share_percent'], 'tier' => $r['tier'], 'status' => 'active', 'added_by' => $by->id,
            ]));
            $fmt = fn ($c) => $c->map(fn ($b) => ['name' => $b->name, 'tier' => $b->tier, 'share' => $b->share_percent])->all();
            Audit::record('beneficiaries.updated', $account, $fmt($old), $fmt($new), $by->id);

            return $new;
        });
    }
}
