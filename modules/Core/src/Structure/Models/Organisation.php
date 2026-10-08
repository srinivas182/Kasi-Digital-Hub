<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Identity\Models\User;

/**
 * Employer, training provider, partner, funder, hub operator or the platform owner.
 *
 * @property string $id
 * @property string $type
 * @property string $name
 * @property string|null $registration_number
 * @property string $verification_status
 * @property CarbonImmutable|null $verified_at
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property int|null $municipality_id
 * @property string|null $address
 */
final class Organisation extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const TYPES = ['employer', 'training_provider', 'partner', 'funder', 'hub_operator', 'platform'];

    protected $fillable = ['type', 'name', 'registration_number', 'verification_status', 'verified_at', 'contact_email', 'contact_phone', 'municipality_id', 'address'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_members')->withPivot('title')->withTimestamps();
    }
}
