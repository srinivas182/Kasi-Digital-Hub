<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class DisputeResolved extends MarketEvent
{
    public const DESCRIPTION = 'A KasiMarket dispute was settled or decided.';

    public const NAME = 'market.dispute.resolved';
}
