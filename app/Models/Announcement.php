<?php

namespace App\Models;

use App\Services\Features;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public const TYPES = ['info' => 'Information', 'warning' => 'Warning', 'maintenance' => 'Maintenance'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'dismissible' => 'boolean'];
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Live notices this member should see: right audience, not dismissed. */
    public static function forMember(User $user)
    {
        $dismissed = \DB::table('announcement_dismissals')->where('user_id', $user->id)->pluck('announcement_id');

        return static::live()->whereNotIn('id', $dismissed)->orderByDesc('id')->get()
            ->filter(fn ($a) => $a->audience === 'everyone' || Features::enabled($user, $a->audience))->values();
    }
}
