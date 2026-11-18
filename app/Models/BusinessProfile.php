<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    public const ENTITY_TYPES = ['sole_proprietor' => 'Sole proprietor', 'llc' => 'LLC', 'corporation' => 'Corporation', 'partnership' => 'Partnership', 'nonprofit' => 'Nonprofit'];

    /** What the business accounts are, as the member picks them. */
    public const ACCOUNT_CHOICES = ['both' => 'Business checking and savings', 'checking' => 'Business checking only', 'savings' => 'Business savings only'];

    protected $fillable = ['user_id', 'legal_name', 'entity_type', 'ein', 'industry', 'started_year', 'address_line1', 'city', 'state', 'postal_code'];

    protected $hidden = ['ein'];

    protected function casts(): array
    {
        return ['ein' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entityLabel(): string
    {
        return self::ENTITY_TYPES[$this->entity_type] ?? $this->entity_type;
    }

    /** "••-•••6789" for screens. */
    public function maskedEin(): string
    {
        return '••-•••'.substr(preg_replace('/\D/', '', (string) $this->ein), -4);
    }

    public function yearsInBusiness(): int
    {
        return max(0, (int) now()->year - (int) $this->started_year);
    }
}
