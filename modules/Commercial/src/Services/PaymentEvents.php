<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Illuminate\Support\Facades\DB;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Payments\PaymentGateway;

/**
 * Provider notifications (ADR-027): signature checked by the driver, stored once per provider event id,
 * and applied idempotently - a repeated or late notification never double-counts.
 */
final readonly class PaymentEvents
{
    public function __construct(private PaymentGateway $gateway, private Orders $orders) {}

    /** @param array<string, string> $headers */
    public function handle(array $headers, string $body): bool
    {
        $event = $this->gateway->parse($headers, $body);
        if ($event === null) {
            return false;
        }
        $inserted = DB::table('com_provider_events')->insertOrIgnore(['provider' => $this->gateway->name(), 'event_id' => $event['id'], 'type' => $event['type'],
            'payload' => $body, 'created_at' => now()]);
        if ($inserted === 0) {
            return true; // already handled
        }
        $order = Order::query()->with('product')->where('provider_ref', $event['reference'])->first();
        if ($order instanceof Order) {
            match (true) {
                $event['type'] === 'charge.success' && $event['status'] === 'success' => $this->orders->markPaid($order, $event['amount']),
                in_array($event['type'], ['charge.failed', 'charge.abandoned'], true) => $this->orders->markFailed($order),
                default => null,
            };
        }
        DB::table('com_provider_events')->where('provider', $this->gateway->name())->where('event_id', $event['id'])->update(['processed_at' => now()]);

        return true;
    }
}
