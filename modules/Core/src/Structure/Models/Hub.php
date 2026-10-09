<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * A physical Kasi Digital Hub. Belongs to a city (metro or local municipality) and
 * is switched to a package of portals it can deliver locally.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $phone
 * @property string|null $email
 * @property int $municipality_id
 * @property string|null $place_id
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property array<string, string>|null $opening_hours
 * @property string $status
 * @property string $package
 * @property string|null $operator_organisation_id
 * @property list<string>|null $trusted_ips
 * @property string|null $kiosk_token_hash
 * @property-read Municipality $municipality
 */
final class Hub extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['planned', 'live', 'paused'];

    protected $fillable = ['code', 'name', 'slug', 'description', 'phone', 'email', 'municipality_id', 'place_id', 'address', 'latitude', 'longitude', 'opening_hours', 'status', 'package', 'operator_organisation_id', 'trusted_ips'];

    protected $hidden = ['kiosk_token_hash'];

    /** Cache key for every hub's trusted internet connections (used by the sign-in code limits). */
    public const TRUSTED_IPS_CACHE = 'kasi:hubs:trusted-ips';

    protected static function booted(): void
    {
        self::saved(static fn () => Cache::forget(self::TRUSTED_IPS_CACHE));
        self::deleted(static fn () => Cache::forget(self::TRUSTED_IPS_CACHE));
    }

    /**
     * Every trusted hub connection (IP or CIDR range), cached for an hour.
     *
     * @return list<string>
     */
    public static function allTrustedIps(): array
    {
        /** @var list<string> */
        return Cache::remember(self::TRUSTED_IPS_CACHE, 3600, static fn (): array => self::query()
            ->whereNotNull('trusted_ips')->pluck('trusted_ips')->flatten()->filter(static fn (mixed $ip): bool => is_string($ip))->unique()->values()->all());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'trusted_ips' => 'array'];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'operator_organisation_id');
    }

    /** @return HasMany<HubModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(HubModule::class);
    }
}
