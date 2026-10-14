<?php

declare(strict_types=1);

namespace Modules\Commercial\Payments;

use Illuminate\Support\Facades\Http;
use Modules\Commercial\Models\Order;
use RuntimeException;

/**
 * Paystack (South Africa): transactions with split settlement to a hub sub-account, HMAC-SHA512 signed
 * webhooks, refunds and settlement lookups. Amounts in cents. Switched on only with live keys and the
 * legal sign-off (KASI_PAYMENTS_DRIVER=paystack).
 */
final readonly class PaystackGateway implements PaymentGateway
{
    private const API = 'https://api.paystack.co';

    public function __construct(private string $secret) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function start(Order $order, ?string $hubSubaccount, int $hubShareCents, string $email, string $returnUrl): array
    {
        $payload = ['email' => $email, 'amount' => $order->total_cents, 'currency' => 'ZAR', 'reference' => $order->number, 'callback_url' => $returnUrl,
            'metadata' => ['order' => $order->id]];
        if ($hubSubaccount !== null && $hubShareCents > 0) {
            // The sub-account (hub) receives the amount minus transaction_charge; the main account (Kasi) keeps the charge.
            $payload += ['subaccount' => $hubSubaccount, 'transaction_charge' => $order->total_cents - $hubShareCents, 'bearer' => 'account'];
        }
        $response = Http::withToken($this->secret)->timeout(20)->post(self::API.'/transaction/initialize', $payload);
        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Payment could not be started');
        }

        return ['url' => (string) $response->json('data.authorization_url'), 'reference' => (string) $response->json('data.reference')];
    }

    public function parse(array $headers, string $body): ?array
    {
        if (! hash_equals(hash_hmac('sha512', $body, $this->secret), (string) ($headers['x-paystack-signature'] ?? ''))) {
            return null;
        }
        $data = (array) json_decode($body, true);
        $tx = (array) ($data['data'] ?? []);

        return ['id' => (string) ($tx['id'] ?? '').':'.(string) ($data['event'] ?? ''), 'type' => (string) ($data['event'] ?? ''),
            'reference' => (string) ($tx['reference'] ?? ''), 'amount' => (int) ($tx['amount'] ?? 0), 'status' => (string) ($tx['status'] ?? '')];
    }

    public function refund(Order $order, int $amountCents): string
    {
        $response = Http::withToken($this->secret)->timeout(20)->post(self::API.'/refund', ['transaction' => $order->provider_ref, 'amount' => $amountCents]);
        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Refund failed');
        }

        return (string) $response->json('data.id');
    }

    public function settlements(string $date): array
    {
        $response = Http::withToken($this->secret)->timeout(30)->get(self::API.'/transaction', ['from' => $date, 'to' => $date, 'status' => 'success', 'perPage' => 500]);

        return array_values(array_map(static fn (array $t): array => ['reference' => (string) $t['reference'], 'amount' => (int) $t['amount']], (array) $response->json('data', [])));
    }
}
