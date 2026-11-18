<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class KycDocument extends Model
{
    public const TYPES = ['government_id' => 'Government photo ID', 'proof_of_address' => 'Proof of address', 'business_document' => 'Business document'];

    /** Every member gives an ID and proof of address. A member with a business also gives a business document. */
    public static function requiredFor(User $user): array
    {
        $profile = $user->businessProfile;

        return $profile && ! $profile->isPending() ? array_keys(self::TYPES) : ['government_id', 'proof_of_address'];
    }

    /** What the member can upload on the verification page. A business waiting for approval still needs its document. */
    public static function uploadableFor(User $user): array
    {
        return $user->businessProfile()->exists() ? array_keys(self::TYPES) : ['government_id', 'proof_of_address'];
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decisions(): MorphMany
    {
        return $this->morphMany(Decision::class, 'decidable');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
