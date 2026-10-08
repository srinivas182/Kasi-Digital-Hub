<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Access\BelongsToHub;
use Modules\Core\Database\Factories\UserFactory;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * A person's single account across every portal (phone + PIN; staff add an authenticator).
 *
 * @property string $id
 * @property string $phone
 * @property CarbonImmutable|null $phone_verified_at
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $first_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property CarbonImmutable $date_of_birth
 * @property string $preferred_locale
 * @property string|null $pin
 * @property int $pin_failed_attempts
 * @property CarbonImmutable|null $pin_locked_until
 * @property string $status
 * @property string $age_band
 * @property bool $whatsapp_opt_in
 * @property bool $two_factor_required
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $deletion_requested_at
 * @property string|null $home_hub_id
 * @property int|null $province_id
 * @property int|null $municipality_id
 * @property string|null $place_name
 * @property int $access_version
 */
final class User extends Authenticatable
{
    use BelongsToHub;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_GUARDIAN = 'pending_guardian';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DELETION_REQUESTED = 'deletion_requested';

    protected $fillable = [
        'phone', 'phone_verified_at', 'email', 'email_verified_at', 'first_name', 'last_name', 'preferred_name',
        'date_of_birth', 'preferred_locale', 'pin', 'status', 'age_band', 'whatsapp_opt_in', 'two_factor_required',
        'home_hub_id', 'province_id', 'municipality_id', 'place_name',
    ];

    protected $hidden = ['pin', 'remember_token'];

    /** Accounts never use the "remember me" token column; remembered devices are handled by DeviceManager. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->pin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'date_of_birth' => 'immutable_date',
            'pin' => 'hashed',
            'pin_locked_until' => 'immutable_datetime',
            'whatsapp_opt_in' => 'boolean',
            'two_factor_required' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'deletion_requested_at' => 'immutable_datetime',
        ];
    }

    public function displayName(): string
    {
        return $this->preferred_name ?: $this->first_name;
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function age(): int
    {
        return (int) $this->date_of_birth->diffInYears(CarbonImmutable::now());
    }

    public function isMinor(): bool
    {
        return $this->age_band === 'minor';
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** @return HasOne<StaffTwoFactor, $this> */
    public function twoFactor(): HasOne
    {
        return $this->hasOne(StaffTwoFactor::class);
    }

    /** @return HasMany<GuardianConsent, $this> */
    public function guardianConsents(): HasMany
    {
        return $this->hasMany(GuardianConsent::class);
    }

    /** @return BelongsTo<Hub, $this> */
    public function homeHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'home_hub_id');
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** People are scoped to staff through their home hub. */
    protected function hubColumn(): string
    {
        return 'home_hub_id';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
