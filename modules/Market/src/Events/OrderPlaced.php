<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class OrderPlaced extends MarketEvent
{
    public const DESCRIPTION = 'A buyer placed a KasiMarket order (in the app or on WhatsApp).';

    public const NAME = 'market.order.placed';
}
