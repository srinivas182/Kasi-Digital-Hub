<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class DisputeOpened extends MarketEvent
{
    public const DESCRIPTION = 'A buyer opened a dispute about a KasiMarket order.';

    public const NAME = 'market.dispute.opened';
}
