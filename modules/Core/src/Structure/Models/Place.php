<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A village, township, town or suburb, added as hubs open (no paid map service needed).
 *
 * @property string $id
 * @property int $municipality_id
 * @property string $name
 * @property string $kind
 * @property string|null $latitude
 * @property string|null $longitude
 */
final class Place extends Model
{
    use HasUlids;

    protected $fillable = ['municipality_id', 'name', 'kind', 'latitude', 'longitude'];

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
