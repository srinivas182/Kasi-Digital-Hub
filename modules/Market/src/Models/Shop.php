<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $business_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $hub_id
 * @property string|null $area
 * @property string|null $whatsapp
 * @property bool $whatsapp_public
 * @property array<string, string>|null $hours
 * @property bool $away
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property string $status
 * @property int $approved_products
 * @property string|null $opened_by
 */
final class Shop extends Model
{
    use HasUlids;

    protected $table = 'market_shops';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['hours' => 'array', 'whatsapp_public' => 'boolean', 'away' => 'boolean', 'approved_products' => 'integer'];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
