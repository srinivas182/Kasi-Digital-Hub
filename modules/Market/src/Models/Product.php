<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $shop_id
 * @property int $category_id
 * @property string $title
 * @property string|null $description
 * @property int $price_cents
 * @property string $unit
 * @property string $stock_type
 * @property int|null $stock
 * @property string $status
 * @property string|null $review_reason
 * @property int $views
 * @property CarbonImmutable|null $published_at
 * @property-read Shop $shop
 * @property-read Collection<int, ProductPhoto> $photos
 * @property-read Collection<int, ProductOption> $options
 */
final class Product extends Model
{
    use HasUlids;

    public const UNITS = ['each', 'kg', 'hour', 'session', 'pack', 'metre'];

    public const STOCK_TYPES = ['quantity', 'made_to_order', 'service'];

    protected $table = 'market_products';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'stock' => 'integer', 'views' => 'integer', 'published_at' => 'immutable_datetime'];
    }

    /**
     * Visible to everyone: published, in an open shop.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('market_products.status', 'published')->whereHas('shop', fn (Builder $s) => $s->where('status', 'open'));
    }

    public function soldOut(): bool
    {
        return $this->stock_type === 'quantity' && (int) $this->stock <= 0;
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return HasMany<ProductPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('position');
    }

    /** @return HasMany<ProductOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }
}
