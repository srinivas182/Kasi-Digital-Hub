<?php

declare(strict_types=1);

namespace Modules\Market\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Order;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Cart;
use Modules\Market\Services\Ordering;

/** Cart and orders for buyers, and order handling for traders (S25). */
final class OrderController
{
    public function __construct(private readonly Cart $cart, private readonly Ordering $ordering, private readonly BusinessFacts $businesses) {}

    // --- Buyer --------------------------------------------------------------------------------

    public function cart(Request $request): Response
    {
        [$user] = $this->buyer($request);

        return Inertia::render('Market/Cart', [
            'groups' => array_map(fn (array $g): array => [
                'shop' => ['id' => $g['shop']->id, 'name' => $g['shop']->name, 'slug' => $g['shop']->slug, 'collection' => (bool) $g['shop']->getAttribute('offers_collection'),
                    'collectionNote' => $g['shop']->getAttribute('collection_note'), 'delivery' => (bool) $g['shop']->getAttribute('offers_delivery'),
                    'deliveryArea' => $g['shop']->getAttribute('delivery_area'), 'deliveryFee' => (int) $g['shop']->getAttribute('delivery_fee_cents'),
                    'minOrder' => (int) $g['shop']->getAttribute('min_order_cents'), 'whatsapp' => $g['shop']->whatsapp_public && $g['shop']->whatsapp !== null,
                    'online' => Ordering::onlineReady($g['shop']), 'cash' => (bool) ($g['shop']->getAttribute('accepts_cash') ?? true),
                    'hubCollection' => Ordering::hubCollection($g['shop']) ? (string) DB::table('hubs')->where('id', $g['shop']->hub_id)->value('name') : null,
                    'zones' => (array) json_decode((string) ($g['shop']->getAttributes()['delivery_zones'] ?? '[]'), true),
                    'freeOver' => $g['shop']->getAttribute('free_delivery_over_cents') !== null ? (int) $g['shop']->getAttribute('free_delivery_over_cents') : null],
                'lines' => array_map(static fn (array $l): array => ['id' => $l['id'], 'productId' => $l['product']->id, 'title' => $l['product']->title, 'option' => $l['option']?->label,
                    'quantity' => $l['quantity'], 'unit_price_cents' => $l['unit_price_cents'], 'problem' => $l['problem']], $g['lines']),
                'items_cents' => $g['items_cents'],
            ], $this->cart->contents($user)),
            'canOrder' => $user->date_of_birth->age >= Ordering::MIN_AGE,
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        [$user] = $this->buyer($request);
        $data = $request->validate(['option_id' => ['nullable', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY]]);
        try {
            $this->cart->add($user, $product, isset($data['option_id']) ? (int) $data['option_id'] : null, (int) $data['quantity']);
        } catch (DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back()->with('status', __('market.cart.added'));
    }

    public function setQuantity(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]]);
        $this->cart->set($this->buyer($request)[0], $item, (int) $data['quantity']);

        return back();
    }

    public function place(Request $request, Shop $shop): \Symfony\Component\HttpFoundation\Response
    {
        [$user, $facilitator] = $this->buyer($request);
        $data = $request->validate(['fulfilment' => ['required', Rule::in(['collection', 'delivery', 'hub'])], 'address' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:500'], 'channel' => ['required', Rule::in(['app', 'whatsapp'])], 'share_phone' => ['boolean'],
            'payment' => ['nullable', Rule::in(['cash', 'online'])], 'zone' => ['nullable', 'string', 'max:40']]);
        try {
            $order = $this->ordering->place($user, $shop, $data['fulfilment'], $data['address'] ?? null, $data['note'] ?? null, $data['channel'], $facilitator,
                $data['payment'] ?? 'cash', $data['zone'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
        $order->forceFill(['phone_shared' => (bool) ($data['share_phone'] ?? false)])->save();
        if ($order->payment === 'online') {
            // To the payment page (the demo page in CI and demos); back to the order afterwards.
            return Inertia::location($this->ordering->startPayment($order->load('shop'), $user));
        }

        return to_route('market.orders.show', $order)->with('status', __($data['channel'] === 'whatsapp' ? 'market.order.placed_whatsapp' : 'market.order.placed'));
    }

    public function mine(Request $request): Response
    {
        [$user] = $this->buyer($request);

        return Inertia::render('Market/Orders', [
            'orders' => Order::query()->with('shop')->where('buyer_id', $user->id)->latest()->limit(100)->get()->map(static fn (Order $o): array => [
                'id' => $o->id, 'number' => $o->number, 'shop' => $o->shop->name, 'status' => $o->status, 'total_cents' => $o->total_cents, 'placed' => $o->created_at->toIso8601String()]),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $user = $this->user($request);
        $isBuyer = $order->buyer_id === $user->id;
        $isTrader = $this->businesses->isMember((string) $order->shop()->value('business_id'), $user);
        abort_unless($isBuyer || $isTrader, 404);
        $order->load(['items', 'shop', 'buyer']);
        $shop = $order->shop;

        return Inertia::render('Market/Order', [
            'order' => [
                'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'reason' => $order->status_reason, 'eta' => $order->eta, 'channel' => $order->channel,
                'fulfilment' => $order->fulfilment, 'address' => $order->delivery_address, // only buyer and trader reach this page 'note' => $order->note,
                'items_cents' => $order->items_cents, 'fee_cents' => $order->delivery_fee_cents, 'total_cents' => $order->total_cents, 'placed' => $order->created_at->toIso8601String(),
                'items' => $order->items->map(static fn ($i): array => ['title' => $i->title, 'option' => $i->option_label, 'quantity' => $i->quantity, 'unit_price_cents' => $i->unit_price_cents])->values(),
                'buyerConfirmed' => $order->buyer_confirmed, 'payment' => $order->getAttribute('payment'), 'paymentStatus' => $order->getAttribute('payment_status'),
                'refunded_cents' => (int) $order->getAttribute('refunded_cents'),
                'collectionHub' => $order->getAttribute('collection_hub_id') !== null ? DB::table('hubs')->where('id', $order->getAttribute('collection_hub_id'))->first(['name', 'address']) : null,
            ],
            'shop' => ['name' => $shop->name, 'slug' => $shop->slug, 'collectionNote' => $shop->getAttribute('collection_note'),
                'whatsapp' => $shop->whatsapp_public ? $shop->whatsapp : null],
            // The trader sees the buyer's first name, and their phone only if the buyer chose to share it.
            'buyer' => $isTrader ? ['name' => $order->buyer->first_name, 'phone' => $order->phone_shared ? $order->buyer->phone : null,
                'phoneMasked' => SaFormat::maskedPhone($order->buyer->phone)] : null,
            'viewer' => $isTrader ? 'trader' : 'buyer',
            'moves' => $isTrader ? (Order::TRADER_MOVES[$order->status] ?? []) : [],
            'messages' => DB::table('market_order_messages')->where('order_id', $order->id)
                ->where(fn ($q) => $q->where('status', 'sent')->orWhere('sender_id', $user->id))->orderBy('created_at')->get(['id', 'sender_id', 'body', 'status', 'created_at'])
                ->map(static fn (object $m): array => ['id' => (string) $m->id, 'mine' => $m->sender_id === $user->id, 'body' => (string) $m->body, 'held' => $m->status === 'held', 'at' => (string) $m->created_at]),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($order->buyer_id === $user->id, 404);

        return $this->attempt(fn () => $this->ordering->cancelByBuyer($order, $user, $request->string('reason')->toString() ?: null), 'market.order.cancelled_ok');
    }

    public function received(Request $request, Order $order): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($order->buyer_id === $user->id, 404);

        return $this->attempt(fn () => $this->ordering->confirmReceived($order, $user), 'market.order.thanks');
    }

    public function message(Request $request, Order $order): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($order->buyer_id === $user->id || $this->businesses->isMember((string) $order->shop()->value('business_id'), $user), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $status = $this->ordering->message($order, $user, $data['body']);

        return back()->with('status', __($status === 'held' ? 'market.message.held' : 'market.message.sent'));
    }

    // --- Trader -------------------------------------------------------------------------------

    public function traderOrders(Request $request): Response
    {
        $shop = $this->myShop($request);
        $orders = Order::query()->with('buyer')->where('shop_id', $shop->id)->latest()->limit(200)->get();

        return Inertia::render('Market/Trader/Orders', [
            'orders' => $orders->map(static fn (Order $o): array => ['id' => $o->id, 'number' => $o->number, 'buyer' => $o->buyer->first_name, 'status' => $o->status,
                'fulfilment' => $o->fulfilment, 'total_cents' => $o->total_cents, 'placed' => $o->created_at->toIso8601String(), 'eta' => $o->eta]),
            'counts' => ['new' => $orders->where('status', 'placed')->count(), 'active' => $orders->whereIn('status', ['accepted', 'ready', 'out_for_delivery'])->count()],
        ]);
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->myShop($request);
        abort_unless($order->shop_id === $shop->id, 404);
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:300']]);

        return $this->attempt(fn () => $this->ordering->partialRefund($order, $this->user($request), (int) $data['amount_cents'], $data['reason']), 'market.refund.done');
    }

    public function move(Request $request, Order $order): RedirectResponse
    {
        $shop = $this->myShop($request);
        abort_unless($order->shop_id === $shop->id, 404);
        $data = $request->validate(['to' => ['required', 'string', 'max:20'], 'reason' => ['nullable', 'string', 'max:300'], 'eta' => ['nullable', 'string', 'max:60']]);

        return $this->attempt(fn () => $this->ordering->move($order, $this->user($request), $data['to'], $data['reason'] ?? null, $data['eta'] ?? null), 'market.order.updated');
    }

    /**
     * The buyer: the person a hub facilitator is helping (assisted session), or the signed-in person; the
     * facilitator is recorded as the one who placed the order (S25, as in KasiWork).
     *
     * @return array{0: User, 1: User|null}
     */
    private function buyer(Request $request): array
    {
        $user = $this->user($request);
        $helped = app(AssistedSession::class)->person($user);

        return [$helped ?? $user, $helped !== null ? $user : null];
    }

    private function myShop(Request $request): Shop
    {
        $shop = Shop::query()->whereIn('business_id', array_column($this->businesses->businessesOf($this->user($request)), 'id'))->first();
        abort_unless($shop instanceof Shop, 404);

        return $shop;
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
