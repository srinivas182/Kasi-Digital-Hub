<?php

declare(strict_types=1);

namespace Modules\Commercial\Payments;

use Modules\Commercial\Models\Order;

/**
 * A payment provider that supports split settlement (ADR-027). Our ledger is the source of truth; the
 * provider moves the money.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Start a payment. The hub's share is routed to its sub-account; Kasi's share stays with the main account.
     *
     * @return array{url: string, reference: string}
     */
    public function start(Order $order, ?string $hubSubaccount, int $hubShareCents, string $email, string $returnUrl): array;

    /**
     * Verify and decode a provider notification. Returns null when the signature is wrong.
     *
     * @param  array<string, string>  $headers
     * @return array{id: string, type: string, reference: string, amount: int, status: string}|null
     */
    public function parse(array $headers, string $body): ?array;

    public function refund(Order $order, int $amountCents): string;

    /** @return list<array{reference: string, amount: int}> settled payments for a day */
    public function settlements(string $date): array;
}
