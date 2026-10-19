<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Media\WebpImage;
use Modules\Market\Events\ProductPublished;
use Modules\Market\Models\Product;
use Modules\Market\Models\ProductPhoto;
use Modules\Market\Models\Shop;

/** Products and services in a shop (ADR-032): quick add from a phone, options, stock, up to 4 small photos. */
final readonly class Products
{
    public const MAX_PHOTOS = 4;

    public function __construct(private Safety $safety, private AuditLogger $audit) {}

    /**
     * @param  array{title: string, category_id: int, price_cents: int, description?: string|null, unit?: string, stock_type?: string, stock?: int|null}  $data
     * @param  list<array{label: string, price_cents?: int|null, stock?: int|null}>  $options
     */
    public function save(Shop $shop, User $by, array $data, array $options = [], ?Product $product = null): Product
    {
        $values = [
            'title' => trim($data['title']), 'category_id' => $data['category_id'], 'price_cents' => $data['price_cents'], 'description' => $data['description'] ?? null,
            'unit' => $data['unit'] ?? 'each', 'stock_type' => $data['stock_type'] ?? 'quantity',
            'stock' => ($data['stock_type'] ?? 'quantity') === 'quantity' ? (int) ($data['stock'] ?? 0) : null,
        ];
        $product = DB::transaction(function () use ($shop, $values, $options, $product): Product {
            if ($product === null) {
                $product = Product::query()->create([...$values, 'shop_id' => $shop->id, 'status' => 'draft', 'views' => 0]);
            } else {
                $product->fill($values);
                // Editing a published product's words sends it through the checks again.
                if ($product->status === 'published' && $product->isDirty(['title', 'description'])) {
                    $product->status = 'draft';
                }
                $product->save();
            }
            $product->options()->delete();
            foreach ($options as $i => $option) {
                if (trim((string) $option['label']) !== '') {
                    $product->options()->create(['label' => trim((string) $option['label']), 'price_cents' => $option['price_cents'] ?? null, 'stock' => $option['stock'] ?? null, 'position' => $i]);
                }
            }

            return $product;
        });
        $this->audit->record('market.product_saved', meta: ['product' => $product->id], actor: $by);

        return $product->refresh();
    }

    public function photo(Product $product, UploadedFile $file, string $alt): ProductPhoto
    {
        if ($product->photos()->count() >= self::MAX_PHOTOS) {
            throw new DomainException(__('market.photo.max', ['max' => self::MAX_PHOTOS]));
        }
        $contents = (string) file_get_contents((string) $file->getRealPath());
        $dir = "market/{$product->shop_id}/{$product->id}";
        $name = Str::random(10);
        Storage::disk('public')->put("{$dir}/{$name}-s.webp", WebpImage::make($contents, 320, 65));
        Storage::disk('public')->put("{$dir}/{$name}-m.webp", WebpImage::make($contents, 800, 70));

        return $product->photos()->create(['small_path' => "{$dir}/{$name}-s.webp", 'medium_path' => "{$dir}/{$name}-m.webp", 'alt' => trim($alt),
            'position' => (int) $product->photos()->max('position') + 1]);
    }

    public function removePhoto(ProductPhoto $photo): void
    {
        Storage::disk('public')->delete([$photo->small_path, $photo->medium_path]);
        $photo->delete();
    }

    /**
     * Publish: refused, held for review, or live.
     *
     * @return array{status: string, reason: string|null}
     */
    public function publish(Product $product, User $by): array
    {
        if ($product->price_cents <= 0) {
            throw new DomainException(__('market.product.price_needed'));
        }
        $decision = $this->safety->check($product->loadMissing('shop'), $by);
        $product->forceFill(['status' => $decision['status'], 'review_reason' => $decision['reason'],
            'published_at' => $decision['status'] === 'published' ? ($product->published_at ?? now()) : $product->published_at])->save();
        if ($decision['status'] === 'published') {
            $this->live($product, $by);
        }
        $this->audit->record('market.product_'.$decision['status'], meta: ['product' => $product->id, 'reason' => $decision['reason']], actor: $by);

        return $decision;
    }

    /** A product went live (directly or after review). */
    public function live(Product $product, ?User $by): void
    {
        Shop::query()->whereKey($product->shop_id)->where('approved_products', '<', Safety::FIRST_REVIEWED)->increment('approved_products');
        $owner = User::query()->find($product->shop->opened_by);
        if ($owner instanceof User) {
            event(new ProductPublished($owner, $by?->id, ['product' => $product->id, 'shop' => $product->shop_id]));
        }
    }

    public function hide(Product $product, User $by, bool $hidden): void
    {
        if (! in_array($product->status, ['published', 'hidden'], true)) {
            return;
        }
        $product->forceFill(['status' => $hidden ? 'hidden' : 'published'])->save();
        $this->audit->record('market.product_'.($hidden ? 'hidden' : 'shown'), meta: ['product' => $product->id], actor: $by);
    }
}
