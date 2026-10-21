<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Modules\Core\Enterprise\BusinessProducts;

/**
 * The WhatsApp catalogue (ADR-038): up to 30 listed KasiMarket products (through the core BusinessProducts contract)
 * as branded images drawn on the phone, plus a ready-to-send message with prices and links. Nothing is sent for them.
 */
final readonly class Catalogue
{
    public const MAX = 30;

    public function __construct(private BusinessProducts $products) {}

    /** @return list<array{id: string, title: string, price_cents: int, unit: string, photo: string|null, url: string}> */
    public function available(string $businessId): array
    {
        return $this->products->products($businessId, 60);
    }

    /**
     * The chosen products (in the order chosen, only this business's listed ones) and the message to send.
     *
     * @param  list<string>  $ids
     * @return array{items: list<array{id: string, title: string, price_cents: int, unit: string, photo: string|null, url: string}>, message: string}
     */
    public function build(string $businessId, array $ids, string $businessName): array
    {
        $all = collect($this->products->products($businessId, 60))->keyBy('id');
        $items = array_values(array_filter(array_map(static fn (string $id) => $all->get($id), array_slice(array_values(array_unique($ids)), 0, self::MAX))));
        $shop = $this->products->shop($businessId);
        $lines = [__('brand.catalogue.message_intro', ['business' => $businessName])];
        foreach ($items as $i) {
            $lines[] = '• '.$i['title'].' - R'.number_format($i['price_cents'] / 100, 2, '.', ' ').' '.__('market.unit.'.$i['unit']).' '.$i['url'];
        }
        if ($shop !== null) {
            $lines[] = __('brand.catalogue.message_shop', ['url' => $shop['url']]);
        }

        return ['items' => $items, 'message' => implode("\n", $lines)];
    }
}
