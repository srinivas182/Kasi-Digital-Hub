<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Metro, district or local municipality. In the platform hierarchy a metro or local
 * municipality is a "city" (national -> province -> city -> hub).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property int $province_id
 * @property int|null $district_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $source
 * @property-read Province $province
 */
final class Municipality extends Model
{
    public const METRO = 'metro';

    public const DISTRICT = 'district';

    public const LOCAL = 'local';

    protected $fillable = ['code', 'name', 'category', 'province_id', 'district_id', 'latitude', 'longitude', 'source'];

    public function isCity(): bool
    {
        return $this->category !== self::DISTRICT;
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<self, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(self::class, 'district_id');
    }

    /** @return HasMany<Hub, $this> */
    public function hubs(): HasMany
    {
        return $this->hasMany(Hub::class);
    }

    /** @return HasMany<Place, $this> */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
