<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFeature;

/** Per-member optional features. A route behind the `feature:<name>` middleware is a 404 for anyone without it. */
final class Features
{
    /** Every feature staff can switch on for a member. Later milestones add cards, crypto, loans and higher limits. */
    public const ALL = [
        'wires' => 'Wire transfers',
    ];

    public static function enabled(User $user, string $feature): bool
    {
        return UserFeature::where('user_id', $user->id)->where('feature', $feature)->whereNull('disabled_at')->exists();
    }

    public static function set(User $member, string $feature, bool $on, User $staff, ?string $note = null): void
    {
        abort_unless(isset(self::ALL[$feature]), 404);
        $current = UserFeature::where('user_id', $member->id)->where('feature', $feature)->whereNull('disabled_at')->first();

        if ($on && ! $current) {
            UserFeature::create(['user_id' => $member->id, 'feature' => $feature, 'enabled_by' => $staff->id, 'enabled_at' => now(), 'note' => $note]);
        } elseif (! $on && $current) {
            $current->update(['disabled_at' => now(), 'disabled_by' => $staff->id, 'note' => $note ?? $current->note]);
        } else {
            return;
        }
        Audit::record($on ? 'feature.enabled' : 'feature.disabled', $member, null, ['feature' => $feature, 'note' => $note], $staff->id);
    }
}
