<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An order line, frozen at ordering time.
 *
 * @property int $id
 * @property string $order_id
 * @property string|null $product_id
 * @property int|null $option_id
 * @property string $title
 * @property string|null $option_label
 * @property int $unit_price_cents
 * @property int $quantity
 * @property bool $reserved
 */
final class OrderItem extends Model
{
    public $timestamps = false;

    protected $table = 'market_order_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_price_cents' => 'integer', 'quantity' => 'integer', 'reserved' => 'boolean'];
    }
}
