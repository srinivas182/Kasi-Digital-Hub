<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 */
final class Province extends Model
{
    protected $fillable = ['code', 'name'];

    /** @return HasMany<Municipality, $this> */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
