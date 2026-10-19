<?php

declare(strict_types=1);

namespace Modules\Market\Search;

use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\Market\Models\Product;

/** Listed products for platform search (public, nothing personal). */
final class MarketSearchSource implements SearchSource
{
    public function type(): string
    {
        return 'market';
    }

    public function all(): iterable
    {
        foreach (Product::query()->listed()->with('shop')->cursor() as $product) {
            yield $this->document($product);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        $product = Product::query()->listed()->with('shop')->find($ref);

        return $product instanceof Product ? $this->document($product) : null;
    }

    private function document(Product $p): SearchDocument
    {
        return new SearchDocument('market', $p->id, $p->title, trim($p->shop->name.' '.$p->description), '/market/products/'.$p->id, 'public', $p->shop->hub_id,
            ['price_cents' => $p->price_cents, 'category' => $p->category_id]);
    }
}
