<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Market\Events\OrderAccepted;
use Modules\Market\Events\OrderCompleted;
use Modules\Market\Events\OrderLapsed;
use Modules\Market\Events\OrderPlaced;
use Modules\Market\Models\Order;
use Modules\Market\Models\OrderItem;
use Modules\Market\Models\Shop;
use Modules\Market\Notifications\MarketNotification;

/**
 * Orders (ADR-033): one per shop, in the app or on WhatsApp; stock reserved when placed and given back if the order
 * doesn't go ahead; the trader responds within 48 hours (reminder at 24) or it lapses; cash on collection or delivery.
 */
final readonly class Ordering
{
    public const MIN_AGE = 18;

    public const REMIND_HOURS = 24;

    public const LAPSE_HOURS = 48;

    public function __construct(private Cart $cart, private AuditLogger $audit, private Notifier $notifier, private ModerationService $moderation) {}

    public function place(User $buyer, Shop $shop, string $fulfilment, ?string $address, ?string $note, string $channel, ?User $placedBy = null): Order
    {
        if ($buyer->date_of_birth->age < self::MIN_AGE) {
            throw new DomainException(__('market.order.age'));
        }
        if ($shop->status !== 'open') {
            throw new DomainException(__('market.cart.unavailable'));
        }
        if (! ($fulfilment === 'collection' ? $shop->getAttribute('offers_collection') : $shop->getAttribute('offers_delivery'))) {
            throw new DomainException(__('market.order.fulfilment_unavailable'));
        }
        if ($fulfilment === 'delivery' && trim((string) $address) === '') {
            throw new DomainException(__('market.order.address_needed'));
        }
        $group = $this->cart->contents($buyer, $shop->id)[0] ?? null;
        if ($group === null || $group['lines'] === []) {
            throw new DomainException(__('market.order.empty'));
        }
        foreach ($group['lines'] as $line) {
            if ($line['problem'] !== null) {
                throw new DomainException(__('market.order.problem', ['item' => $line['product']->title]));
            }
        }
        if ($group['items_cents'] < (int) $shop->getAttribute('min_order_cents')) {
            throw new DomainException(__('market.order.minimum'));
        }
        $fee = $fulfilment === 'delivery' ? (int) $shop->getAttribute('delivery_fee_cents') : 0;

        $order = DB::transaction(function () use ($buyer, $shop, $fulfilment, $address, $note, $channel, $placedBy, $group, $fee): Order {
            $order = Order::query()->create([
                'number' => $this->number(), 'shop_id' => $shop->id, 'buyer_id' => $buyer->id, 'placed_by' => $placedBy?->id, 'channel' => $channel, 'fulfilment' => $fulfilment,
                'delivery_address' => $fulfilment === 'delivery' ? trim((string) $address) : null, 'note' => $note !== null ? mb_substr(trim($note), 0, 500) : null,
                'items_cents' => $group['items_cents'], 'delivery_fee_cents' => $fee, 'total_cents' => $group['items_cents'] + $fee, 'status' => 'placed',
            ]);
            foreach ($group['lines'] as $line) {
                $reserved = false;
                if ($line['product']->stock_type === 'quantity') {
                    // Take the stock only if it's still there (safe when two people order the last one at once).
                    $taken = $line['option'] !== null && $line['option']->stock !== null
                        ? DB::table('market_product_options')->where('id', $line['option']->id)->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity'])
                        : DB::table('market_products')->where('id', $line['product']->id)->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity']);
                    if ($taken === 0) {
                        throw new DomainException(__('market.order.problem', ['item' => $line['product']->title]));
                    }
                    $reserved = true;
                }
                OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $line['product']->id, 'option_id' => $line['option']?->id, 'title' => $line['product']->title,
                    'option_label' => $line['option']?->label, 'unit_price_cents' => $line['unit_price_cents'], 'quantity' => $line['quantity'], 'reserved' => $reserved]);
            }

            return $order;
        });
        $this->cart->clearShop($buyer, $shop->id);
        $this->audit->record('market.order_placed', meta: ['order' => $order->id, 'channel' => $channel], actor: $placedBy ?? $buyer);
        event(new OrderPlaced($buyer, $placedBy?->id, ['order' => $order->id, 'shop' => $shop->id, 'total' => $order->total_cents]));
        $this->tellTrader($order, 'order_new');

        return $order->load('items');
    }

    /** The trader moves the order on. */
    public function move(Order $order, User $by, string $to, ?string $reason = null, ?string $eta = null): void
    {
        if (! in_array($to, Order::TRADER_MOVES[$order->status] ?? [], true)) {
            throw new DomainException(__('market.order.cannot', ['status' => __('market.order.status.'.$order->status)]));
        }
        if (in_array($to, ['declined', 'cancelled'], true) && trim((string) $reason) === '') {
            throw new DomainException(__('market.order.reason_needed'));
        }
        $this->transition($order, $to, $reason, $eta, $by);
        $this->tellBuyer($order, $to === 'cancelled' ? 'order_cancelled_by_trader' : 'order_'.$to); // 'order_cancelled' is the trader's message
    }

    /** The buyer cancels - only before the trader accepts. */
    public function cancelByBuyer(Order $order, User $buyer, ?string $reason): void
    {
        if ($order->status !== 'placed') {
            throw new DomainException(__('market.order.cancel_after_accept'));
        }
        $this->transition($order, 'cancelled', $reason ?? __('market.order.buyer_cancelled'), null, $buyer);
        $this->tellTrader($order, 'order_cancelled');
    }

    public function confirmReceived(Order $order, User $buyer): void
    {
        if (! in_array($order->status, ['ready', 'out_for_delivery', 'completed'], true)) {
            throw new DomainException(__('market.order.not_yet'));
        }
        $order->forceFill(['buyer_confirmed' => true])->save();
        if ($order->status !== 'completed') {
            $this->transition($order, 'completed', null, null, $buyer);
        }
    }

    /**
     * Reminders at 24 hours, lapse at 48 hours (run hourly).
     *
     * @return array{reminded: int, lapsed: int}
     */
    public function sweep(): array
    {
        $done = ['reminded' => 0, 'lapsed' => 0];
        Order::query()->where('status', 'placed')->where('created_at', '<', now()->subHours(self::LAPSE_HOURS))->get()->each(function (Order $o) use (&$done): void {
            $this->transition($o, 'lapsed', __('market.order.lapsed_reason'), null, null);
            $this->tellBuyer($o, 'order_lapsed');
            $done['lapsed']++;
        });
        Order::query()->where('status', 'placed')->whereNull('reminded_at')->where('created_at', '<', now()->subHours(self::REMIND_HOURS))->get()->each(function (Order $o) use (&$done): void {
            $o->forceFill(['reminded_at' => now()])->save();
            $this->tellTrader($o, 'order_reminder');
            $done['reminded']++;
        });

        return $done;
    }

    /** A message about the order; held for review if the checks flag it. */
    public function message(Order $order, User $from, string $body): string
    {
        $id = (string) Str::ulid();
        $check = $this->moderation->check($body, 'market_message', $id, $from, useAi: false);
        DB::table('market_order_messages')->insert(['id' => $id, 'order_id' => $order->id, 'sender_id' => $from->id, 'body' => mb_substr(trim($body), 0, 2000),
            'status' => $check['verdict'] === 'flag' ? 'held' : 'sent', 'created_at' => now()]);
        if ($check['verdict'] !== 'flag') {
            $from->id === $order->buyer_id ? $this->tellTrader($order, 'order_message') : $this->tellBuyer($order, 'order_message');
        }

        return $check['verdict'] === 'flag' ? 'held' : 'sent';
    }

    private function transition(Order $order, string $to, ?string $reason, ?string $eta, ?User $by): void
    {
        DB::transaction(function () use ($order, $to, $reason, $eta): void {
            $closing = in_array($to, ['declined', 'cancelled', 'lapsed', 'completed'], true);
            if (in_array($to, ['declined', 'cancelled', 'lapsed'], true)) {
                $this->release($order);
            }
            $order->forceFill(array_filter([
                'status' => $to, 'status_reason' => $reason !== null ? mb_substr($reason, 0, 300) : $order->status_reason, 'eta' => $eta ?? $order->eta,
                'accepted_at' => $to === 'accepted' ? now() : $order->accepted_at, 'completed_at' => $to === 'completed' ? now() : $order->completed_at,
                'closed_at' => $closing ? now() : null,
            ], static fn ($v) => $v !== null))->save();
        });
        $this->audit->record('market.order_'.$to, meta: ['order' => $order->id, 'reason' => $reason], actor: $by);
        if (in_array($to, ['accepted', 'completed', 'lapsed'], true)) {
            $buyer = User::query()->findOrFail($order->buyer_id);
            $class = ['accepted' => OrderAccepted::class, 'completed' => OrderCompleted::class, 'lapsed' => OrderLapsed::class][$to];
            event(new $class($buyer, $by?->id, ['order' => $order->id, 'shop' => $order->shop_id, 'total' => $order->total_cents]));
        }
    }

    /** Give reserved stock back. */
    private function release(Order $order): void
    {
        foreach (OrderItem::query()->where('order_id', $order->id)->where('reserved', true)->get() as $item) {
            if ($item->option_id !== null && DB::table('market_product_options')->where('id', $item->option_id)->whereNotNull('stock')->exists()) {
                DB::table('market_product_options')->where('id', $item->option_id)->increment('stock', $item->quantity);
            } elseif ($item->product_id !== null) {
                DB::table('market_products')->where('id', $item->product_id)->increment('stock', $item->quantity);
            }
            $item->forceFill(['reserved' => false])->save();
        }
    }

    private function tellTrader(Order $order, string $kind): void
    {
        $owner = User::query()->find($order->shop()->value('opened_by'));
        if ($owner instanceof User) {
            $this->notifier->send($owner, new MarketNotification($kind, ['number' => $order->number], '/market/shop/orders/'.$order->id));
        }
    }

    private function tellBuyer(Order $order, string $kind): void
    {
        $buyer = User::query()->find($order->buyer_id);
        if ($buyer instanceof User) {
            $this->notifier->send($buyer, new MarketNotification($kind, ['number' => $order->number, 'shop' => (string) $order->shop()->value('name')], '/market/orders/'.$order->id));
        }
    }

    private function number(): string
    {
        $year = now()->format('Y');
        $n = DB::table('market_orders')->where('number', 'like', "KM-{$year}-%")->count() + 1;
        do {
            $number = sprintf('KM-%s-%06d', $year, $n++);
        } while (DB::table('market_orders')->where('number', $number)->exists());

        return $number;
    }
}
