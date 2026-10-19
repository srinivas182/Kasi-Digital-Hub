<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class OrderCompleted extends MarketEvent
{
    public const DESCRIPTION = 'A KasiMarket order was handed over and completed.';

    public const NAME = 'market.order.completed';
}
