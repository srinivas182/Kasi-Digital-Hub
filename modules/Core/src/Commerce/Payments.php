<?php

declare(strict_types=1);

namespace Modules\Core\Commerce;

use Modules\Core\Identity\Models\User;

/**
 * Extension point (S26, implemented by the commercial portal): take an online payment for a portal's sale with the
 * split done at source, and refund it. Payment confirmation reaches the portal through its Fulfiller.
 */
interface Payments
{
    /** @return array{url: string, reference: string} where to send the buyer, and the payment reference */
    public function startSale(Sale $sale): array;

    public function refundSale(string $reference, int $amountCents, User $by, string $reason): void;
}
