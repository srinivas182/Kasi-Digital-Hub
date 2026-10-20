<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Identity\Models\User;

/**
 * @property string $id
 * @property string $number
 * @property string $shop_id
 * @property string $buyer_id
 * @property string|null $placed_by
 * @property string $channel
 * @property string $fulfilment
 * @property string|null $delivery_address
 * @property string|null $note
 * @property int $items_cents
 * @property int $delivery_fee_cents
 * @property int $total_cents
 * @property string $status
 * @property string|null $status_reason
 * @property string|null $eta
 * @property bool $phone_shared
 * @property bool $buyer_confirmed
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $created_at
 * @property string $payment
 * @property string $payment_status
 * @property string|null $payment_ref
 * @property int $refunded_cents
 * @property CarbonImmutable|null $pay_by
 * @property string|null $zone
 * @property string|null $collection_hub_id
 * @property string|null $collection_code_hash
 * @property int $code_attempts
 * @property CarbonImmutable|null $at_hub_at
 * @property-read Shop $shop
 * @property-read User $buyer
 * @property-read Collection<int, OrderItem> $items
 */
final class Order extends Model
{
    use HasUlids;

    /** Who may move an order from one status to another. */
    public const TRADER_MOVES = [
        'placed' => ['accepted', 'declined'],
        'accepted' => ['ready', 'out_for_delivery', 'completed', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'out_for_delivery' => ['completed', 'cancelled'],
    ];

    public const OPEN = ['placed', 'accepted', 'ready', 'out_for_delivery', 'at_hub'];

    protected $table = 'market_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items_cents' => 'integer', 'delivery_fee_cents' => 'integer', 'total_cents' => 'integer', 'phone_shared' => 'boolean', 'buyer_confirmed' => 'boolean',
            'reminded_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
