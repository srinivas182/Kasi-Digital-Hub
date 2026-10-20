<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class ReviewAdded extends MarketEvent
{
    public const DESCRIPTION = 'A buyer reviewed a completed KasiMarket order.';

    public const NAME = 'market.review.added';
}
