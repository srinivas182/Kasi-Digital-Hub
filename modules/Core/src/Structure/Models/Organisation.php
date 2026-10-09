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
 * @property string|null $trading_name
 * @property string|null $sector
 * @property string|null $size_band
 * @property string|null $description
 * @property string|null $website
 * @property bool $community
 * @property string|null $registration_document_id
 * @property array<string, bool>|null $verification_checklist
 * @property CarbonImmutable|null $created_at
 */
final class Organisation extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const TYPES = ['employer', 'training_provider', 'partner', 'funder', 'hub_operator', 'platform'];

    protected $fillable = ['type', 'name', 'registration_number', 'verification_status', 'verified_at', 'contact_email', 'contact_phone', 'municipality_id', 'address', 'trading_name', 'sector', 'size_band', 'description', 'website', 'community', 'registration_document_id'];

    /** The name people know (trading name when there is one). */
    public function displayName(): string
    {
        return $this->trading_name ?: $this->name;
    }

    /**
     * Checks the KasiHub team ticks before verifying (config kasi.verification.checklists).
     *
     * @return list<string>
     */
    public function checklistItems(): array
    {
        $key = $this->type.($this->community ? '_community' : '');

        return array_values((array) config("kasi.verification.checklists.{$key}", []));
    }

    public function checklistComplete(): bool
    {
        $ticked = $this->verification_checklist ?? [];

        return collect($this->checklistItems())->every(static fn (string $item): bool => ($ticked[$item] ?? false) === true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'community' => 'boolean', 'verification_checklist' => 'array'];
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
