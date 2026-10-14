<?php

declare(strict_types=1);

namespace Modules\Commercial\Payments;

use Illuminate\Support\Facades\Cache;
use Modules\Commercial\Models\Order;

/**
 * For CI, demos and local work: a payment page on our own site that "pays" or "fails", sending a
 * signed notification like a real provider. Never moves money.
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Order $order, ?string $hubSubaccount, int $hubShareCents, string $email, string $returnUrl): array
    {
        $reference = 'FAKE-'.$order->number;
        Cache::put('fake-pay:'.$reference, ['order' => $order->id, 'return' => $returnUrl, 'amount' => $order->total_cents], now()->addDay());

        return ['url' => url('/commercial/fake-pay/'.$reference), 'reference' => $reference];
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha512', $body, (string) config('app.key'));
    }

    public function parse(array $headers, string $body): ?array
    {
        if (! hash_equals(self::sign($body), (string) ($headers['x-fake-signature'] ?? ''))) {
            return null;
        }
        $data = (array) json_decode($body, true);

        return ['id' => (string) ($data['id'] ?? ''), 'type' => (string) ($data['event'] ?? ''), 'reference' => (string) ($data['reference'] ?? ''),
            'amount' => (int) ($data['amount'] ?? 0), 'status' => (string) ($data['status'] ?? '')];
    }

    public function refund(Order $order, int $amountCents): string
    {
        return 'FAKE-REFUND-'.$order->number.'-'.$amountCents;
    }

    public function settlements(string $date): array
    {
        /** @var list<array{reference: string, amount: int}> */
        return (array) Cache::get('fake-settlements:'.$date, []);
    }
}
