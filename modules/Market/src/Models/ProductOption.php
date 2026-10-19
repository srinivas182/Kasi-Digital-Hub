<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $product_id
 * @property string $label
 * @property int|null $price_cents
 * @property int|null $stock
 * @property int $position
 */
final class ProductOption extends Model
{
    public $timestamps = false;

    protected $table = 'market_product_options';

    protected $guarded = [];
}
