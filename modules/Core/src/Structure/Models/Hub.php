<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 * @property-read Municipality $municipality
 */
final class Hub extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const STATUSES = ['planned', 'live', 'paused'];

    protected $fillable = ['code', 'name', 'slug', 'description', 'phone', 'email', 'municipality_id', 'place_id', 'address', 'latitude', 'longitude', 'opening_hours', 'status', 'package', 'operator_organisation_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opening_hours' => 'array'];
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
