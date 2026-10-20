<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Commercial\Models\LedgerEntry;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Payments\PaymentGateway;
use Modules\Core\Commerce\Fulfiller;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;

/**
 * Orders from checkout, the hub point of sale and invoices (ADR-027): attribution, price within the
 * band, VAT for the merchant of record, the split rule in force, immutable ledger entries, fulfilment by
 * the portal that sold it, and proportional refunds.
 */
final readonly class Orders
{
    public function __construct(
        private Catalogue $catalogue,
        private HubFinance $finance,
        private PaymentGateway $gateway,
        private AuditLogger $audit,
    ) {}

    /**
     * Who gets the credit for an online sale: referral code/QR, then the assisting hub, then the person's
     * nearest (home) hub, else national.
     *
     * @return array{0: Hub|null, 1: string}
     */
    public function attribute(User $buyer, ?string $referralHubCode, ?string $assistingHubId): array
    {
        if ($referralHubCode !== null && ($hub = Hub::query()->where('code', $referralHubCode)->first()) instanceof Hub) {
            return [$hub, 'referral'];
        }
        if ($assistingHubId !== null && ($hub = Hub::query()->find($assistingHubId)) instanceof Hub) {
            return [$hub, 'assisted'];
        }
        if ($buyer->home_hub_id !== null && ($hub = Hub::query()->find($buyer->home_hub_id)) instanceof Hub) {
            return [$hub, 'nearest'];
        }

        return [null, 'national'];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function create(Product $product, int $quantity, ?Hub $hub, string $attribution, string $channel, ?User $buyer, ?string $buyerOrganisationId,
        array $meta, ?User $by = null, ?int $unitPriceCents = null, ?string $buyerName = null): Order
    {
        if (! $product->active) {
            throw new DomainException(__('commercial.order.unavailable'));
        }
        // A hub that isn't ready (or is blocked for unpaid fees) can't take the sale: it goes national.
        if ($hub !== null && $product->merchant === 'hub' && ! $this->finance->canSell($hub)) {
            if ($channel === 'pos') {
                throw new DomainException(__('commercial.hub.not_ready'));
            }
            [$hub, $attribution] = [null, 'national'];
        }
        $band = $this->catalogue->price($product, Catalogue::tier($hub));
        if ($band === null) {
            throw new DomainException(__('commercial.order.no_price'));
        }
        $unit = $unitPriceCents ?? $band['price'];
        if ($unit < $band['floor'] || $unit > $band['ceiling']) {
            throw new DomainException(__('commercial.price.band'));
        }
        $rule = $this->catalogue->rule($product->stream);
        if ($rule === null) {
            throw new DomainException(__('commercial.order.no_rule'));
        }

        $merchant = $hub === null ? 'kasi' : $product->merchant;
        $total = $unit * $quantity;
        $vat = Money::vat($total, $merchant === 'hub' ? $this->finance->vatRegistered($hub) : (bool) config('kasi.commercial.kasi_vat_registered'));
        // Without a hub, the hub's share goes to Kasi.
        $shares = $hub === null ? ['kasi' => $vat['net'], 'hub' => 0, 'partner' => 0]
            : Money::split($vat['net'], $rule['kasi_bp'], $rule['hub_bp'], $product->partner_organisation_id !== null ? $rule['partner_bp'] : 0);
        if ($hub !== null && $product->partner_organisation_id === null && $rule['partner_bp'] > 0) {
            $shares = Money::split($vat['net'], $rule['kasi_bp'] + $rule['partner_bp'], $rule['hub_bp']);
        }

        return Order::query()->create([
            'number' => self::number(), 'product_id' => $product->id, 'hub_id' => $hub?->id, 'attribution' => $attribution, 'buyer_user_id' => $buyer?->id,
            'buyer_organisation_id' => $buyerOrganisationId, 'buyer_name' => $buyerName, 'quantity' => $quantity, 'unit_price_cents' => $unit, 'total_cents' => $total,
            'vat_cents' => $vat['vat'], 'merchant' => $merchant, 'split_rule_id' => $rule['id'], 'shares' => $shares, 'channel' => $channel, 'status' => 'pending',
            'refunded_cents' => 0, 'meta' => $meta, 'created_by' => ($by ?? $buyer)?->id,
        ]);
    }

    /**
     * A marketplace sale (S26): the seller is the merchant (not VAT-registered in Release 1); the stream's split rule
     * reads its "partner" column as the seller's share. The seller also receives `$sellerExtraCents` (e.g. delivery)
     * in full. Without a hub, the hub's share goes to Kasi.
     *
     * @param  array<string, string|int|bool|null>  $meta
     */
    public function createSale(Product $product, int $itemsCents, int $sellerExtraCents, ?Hub $hub, string $payeeRef, User $buyer, array $meta): Order
    {
        $rule = $this->catalogue->rule($product->stream);
        if ($rule === null || $itemsCents <= 0) {
            throw new DomainException(__('commercial.order.no_rule'));
        }
        $split = Money::split($itemsCents, $rule['kasi_bp'] + ($hub === null ? $rule['hub_bp'] : 0), $hub === null ? 0 : $rule['hub_bp'], $rule['partner_bp']);
        $shares = ['kasi' => $split['kasi'], 'hub' => $split['hub'], 'partner' => 0, 'trader' => $split['partner'] + $sellerExtraCents];
        $total = $itemsCents + $sellerExtraCents;

        return Order::query()->create([
            'number' => self::number(), 'product_id' => $product->id, 'hub_id' => $hub?->id, 'attribution' => $hub !== null ? 'hub' : 'national', 'buyer_user_id' => $buyer->id,
            'buyer_organisation_id' => null, 'buyer_name' => null, 'quantity' => 1, 'unit_price_cents' => $total, 'total_cents' => $total, 'vat_cents' => 0, 'merchant' => 'trader',
            'split_rule_id' => $rule['id'], 'shares' => $shares, 'channel' => 'online', 'status' => 'pending', 'refunded_cents' => 0, 'meta' => $meta, 'created_by' => $buyer->id,
            'payee_ref' => $payeeRef,
        ]);
    }

    /** @return array{url: string, reference: string} */
    public function checkout(Order $order, string $email, string $returnUrl): array
    {
        if ($order->getAttribute('payee_ref') !== null) {
            // Marketplace sale: the seller's payout account receives the seller's share directly.
            $subaccount = $order->meta['seller_subaccount'] ?? null;
            $hubShare = $order->shares['trader'] ?? 0;
        } else {
            $subaccount = $order->hub_id !== null ? DB::table('com_hub_finance')->where('hub_id', $order->hub_id)->value('provider_subaccount') : null;
            $hubShare = $order->shares['hub'] + ($order->merchant === 'hub' ? $order->vat_cents : 0);
        }
        $start = $this->gateway->start($order, $subaccount !== null ? (string) $subaccount : null, $hubShare, $email, $returnUrl);
        $order->forceFill(['provider' => $this->gateway->name(), 'provider_ref' => $start['reference']])->save();

        return $start;
    }

    /**
     * Payment confirmed (provider notification, or cash at the point of sale). Idempotent: a second call
     * for a paid order changes nothing.
     */
    public function markPaid(Order $order, ?int $amountCents = null): void
    {
        DB::transaction(function () use ($order, $amountCents): void {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($fresh->status !== 'pending') {
                return;
            }
            if ($amountCents !== null && $amountCents !== $fresh->total_cents) {
                throw new DomainException('Paid amount differs from the order');
            }
            $fresh->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            $this->ledger($fresh, 'sale', 1);
            if ($fresh->channel === 'pos') {
                // Cash stays at the hub: Kasi's and the partner's shares are owed and netted against the next payout.
                $this->entry($fresh, 'royalty_due', 'hub', -($fresh->shares['kasi'] + $fresh->shares['partner']), 'Royalty on cash sale');
            }
            $order->setRawAttributes($fresh->getAttributes(), true);
        });
        $this->fulfil($order->refresh(), true);
        $this->audit->record('commercial.order_paid', meta: ['order' => $order->id, 'total' => $order->total_cents, 'channel' => $order->channel]);
    }

    public function markFailed(Order $order): void
    {
        Order::query()->whereKey($order->id)->where('status', 'pending')->update(['status' => 'failed', 'updated_at' => now()]);
    }

    public function refund(Order $order, int $amountCents, User $by, string $reason): void
    {
        $left = $order->total_cents - $order->refunded_cents;
        if (! in_array($order->status, ['paid', 'part_refunded'], true) || $amountCents <= 0 || $amountCents > $left) {
            throw new DomainException(__('commercial.refund.invalid'));
        }
        if ($order->channel === 'online') {
            $this->gateway->refund($order, $amountCents);
        }
        DB::transaction(function () use ($order, $amountCents): void {
            $net = $order->total_cents - $order->vat_cents;
            $netRefund = $order->vat_cents === 0 ? $amountCents : (int) round($net * $amountCents / $order->total_cents);
            $vat = $amountCents - $netRefund; // net + VAT refunded always equals the refund
            $part = Money::proportionAll($order->shares, $netRefund, $net);
            foreach ([...$part, 'vat' => $vat] as $account => $amount) {
                if ($amount !== 0) {
                    $this->entry($order, 'refund', $account, -$amount, 'Refund');
                }
            }
            if ($order->channel === 'pos') {
                $this->entry($order, 'royalty_due', 'hub', $part['kasi'] + ($part['partner'] ?? 0), 'Royalty reversed on refund');
            }
            $refunded = $order->refunded_cents + $amountCents;
            $order->forceFill(['refunded_cents' => $refunded, 'status' => $refunded === $order->total_cents ? 'refunded' : 'part_refunded'])->save();
        });
        if ($order->status === 'refunded') {
            $this->fulfil($order, false);
        }
        $this->audit->record('commercial.order_refunded', meta: ['order' => $order->id, 'amount' => $amountCents, 'reason' => $reason], actor: $by);
    }

    private function ledger(Order $order, string $kind, int $sign): void
    {
        foreach (['kasi', 'hub', 'partner', 'trader'] as $account) {
            if (($order->shares[$account] ?? 0) !== 0) {
                $this->entry($order, $kind, $account, $sign * $order->shares[$account], $order->number);
            }
        }
        if ($order->vat_cents > 0) {
            $this->entry($order, $kind, 'vat', $sign * $order->vat_cents, 'VAT ('.$order->merchant.')');
        }
    }

    private function entry(Order $order, string $kind, string $account, int $amount, string $memo): void
    {
        LedgerEntry::query()->create(['order_id' => $order->id, 'hub_id' => $order->hub_id,
            'partner_organisation_id' => $account === 'partner' ? $order->product->partner_organisation_id : null,
            'payee_ref' => $account === 'trader' ? $order->getAttribute('payee_ref') : null,
            'kind' => $kind, 'account' => $account, 'amount_cents' => $amount, 'memo' => $memo, 'created_at' => now()]);
    }

    private function fulfil(Order $order, bool $give): void
    {
        foreach (app()->tagged(Fulfiller::TAG) as $fulfiller) {
            /** @var Fulfiller $fulfiller */
            if (in_array($order->product->key, $fulfiller->products(), true)) {
                $give ? $fulfiller->fulfil($order->product->key, $order->quantity, (array) $order->meta) : $fulfiller->revoke($order->product->key, $order->quantity, (array) $order->meta);
            }
        }
    }

    private static function number(): string
    {
        $year = now()->format('Y');
        $n = DB::table('com_orders')->where('number', 'like', "KH-{$year}-%")->count() + 1;
        do {
            $number = sprintf('KH-%s-%06d', $year, $n++);
        } while (DB::table('com_orders')->where('number', $number)->exists());

        return $number;
    }
}
