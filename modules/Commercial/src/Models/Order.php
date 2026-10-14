<?php

declare(strict_types=1);

namespace Modules\Commercial\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $number
 * @property int $product_id
 * @property string|null $hub_id
 * @property string $attribution
 * @property string|null $buyer_user_id
 * @property string|null $buyer_organisation_id
 * @property string|null $buyer_name
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $total_cents
 * @property int $vat_cents
 * @property string $merchant
 * @property int|null $split_rule_id
 * @property array{kasi: int, hub: int, partner: int} $shares
 * @property string $channel
 * @property string $status
 * @property string|null $provider
 * @property string|null $provider_ref
 * @property int $refunded_cents
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 * @property-read Product $product
 */
final class Order extends Model
{
    use HasUlids;

    protected $table = 'com_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['shares' => 'array', 'meta' => 'array', 'quantity' => 'integer', 'unit_price_cents' => 'integer', 'total_cents' => 'integer', 'vat_cents' => 'integer', 'refunded_cents' => 'integer', 'paid_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
