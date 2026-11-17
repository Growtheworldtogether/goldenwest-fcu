<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = ['user_id', 'field_of_membership', 'eligibility_state', 'referred_by_member_number', 'membership_opened_at', 'ssn_last4'];

    protected $hidden = ['ssn_last4'];

    protected function casts(): array
    {
        return ['membership_opened_at' => 'date', 'ssn_last4' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
