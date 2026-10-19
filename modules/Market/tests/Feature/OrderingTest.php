<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Access\Scope;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Identity\Models\User;
use Modules\Core\Insights\Insights;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Market\Database\Seeders\MarketCategorySeeder;
use Modules\Market\Models\Order;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Ordering;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(MarketCategorySeeder::class);
    $this->seed(StepSeeder::class);
    $hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $hub->id, 'module' => 'Market'], ['enabled' => true]);
    $this->trader = User::factory()->create(['first_name' => 'Nomsa', 'last_name' => 'Zwelibanzi-Mkhatshwa', 'home_hub_id' => $hub->id, 'date_of_birth' => now()->subYears(30)]);
    $business = app(Businesses::class)->create($this->trader, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->shop = Shop::query()->create(['business_id' => $business->id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3,
        'hub_id' => $hub->id, 'opened_by' => $this->trader->id, 'offers_collection' => true, 'offers_delivery' => true, 'delivery_fee_cents' => 2000, 'min_order_cents' => 0]);
    $cat = (int) DB::table('market_categories')->value('id');
    $this->oil = Product::query()->create(['shop_id' => $this->shop->id, 'category_id' => $cat, 'title' => 'Hair oil', 'price_cents' => 4500, 'unit' => 'each', 'stock_type' => 'quantity', 'stock' => 10, 'status' => 'published', 'views' => 0]);
    $this->braids = Product::query()->create(['shop_id' => $this->shop->id, 'category_id' => $cat, 'title' => 'Box braids', 'price_cents' => 35000, 'unit' => 'session', 'stock_type' => 'service', 'status' => 'published', 'views' => 0]);
    $this->buyer = User::factory()->create(['first_name' => 'Thandeka', 'last_name' => 'Ngwenya-Sibisi', 'phone' => '+27725550404', 'date_of_birth' => now()->subYears(22)]);
});

function cartAndOrder(TestCase $t, array $o = []): Order
{
    Staff::signIn($t, $t->buyer);
    $t->post("/market/cart/{$t->oil->id}", ['quantity' => 2]);
    $t->post("/market/cart/{$t->braids->id}", ['quantity' => 1]);
    $t->post("/market/cart/shops/{$t->shop->id}/order", ['fulfilment' => 'delivery', 'address' => '12 Mopani Street', 'channel' => 'app', ...$o])->assertSessionHasNoErrors();

    return Order::query()->orderByDesc('number')->firstOrFail(); // numbers always increase; timestamps can tie
}

it('places one order per shop with frozen prices, reserves stock and empties that part of the cart', function (): void {
    $order = cartAndOrder($this);
    expect($order->items_cents)->toBe(2 * 4500 + 35000)->and($order->delivery_fee_cents)->toBe(2000)->and($order->total_cents)->toBe(46000)
        ->and($this->oil->refresh()->stock)->toBe(8)->and(DB::table('market_cart_items')->count())->toBe(0);

    $this->oil->forceFill(['price_cents' => 9999])->save(); // later price changes don't touch the order
    expect($order->items()->where('title', 'Hair oil')->value('unit_price_cents'))->toBe(4500);
});

it('refuses orders with sold-out items and only lets adults order', function (): void {
    Staff::signIn($this, $this->buyer);
    $this->post("/market/cart/{$this->oil->id}", ['quantity' => 11]);
    $this->post("/market/cart/shops/{$this->shop->id}/order", ['fulfilment' => 'collection', 'channel' => 'app'])->assertSessionHasErrors('order');

    $teen = Staff::signIn($this, User::factory()->create(['date_of_birth' => now()->subYears(17)]));
    $this->post("/market/cart/{$this->braids->id}", ['quantity' => 1]);
    $this->post("/market/cart/shops/{$this->shop->id}/order", ['fulfilment' => 'collection', 'channel' => 'app'])->assertSessionHasErrors('order');
    expect(Order::query()->count())->toBe(0);
});

it('never sells the last item twice', function (): void {
    $this->oil->forceFill(['stock' => 1])->save();
    $other = User::factory()->create(['date_of_birth' => now()->subYears(25)]);
    foreach ([$this->buyer, $other] as $person) {
        DB::table('market_cart_items')->insert(['user_id' => $person->id, 'product_id' => $this->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
    app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app');
    expect(fn () => app(Ordering::class)->place($other, $this->shop, 'collection', null, null, 'app'))->toThrow(DomainException::class)
        ->and($this->oil->refresh()->stock)->toBe(0)->and(Order::query()->count())->toBe(1);
});

it('lets the trader move the order on, and gives stock back when it does not go ahead', function (): void {
    $order = cartAndOrder($this);
    Staff::signIn($this, $this->trader);
    $this->post("/market/shop/orders/{$order->id}/move", ['to' => 'completed'])->assertSessionHasErrors('order'); // must accept first
    $this->post("/market/shop/orders/{$order->id}/move", ['to' => 'declined'])->assertSessionHasErrors('order'); // needs a reason
    $this->post("/market/shop/orders/{$order->id}/move", ['to' => 'declined', 'reason' => 'Out of oil today'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('declined')->and($this->oil->refresh()->stock)->toBe(10);

    $second = cartAndOrder($this, ['fulfilment' => 'collection']);
    Staff::signIn($this, $this->trader);
    foreach (['accepted', 'ready', 'completed'] as $to) {
        $this->post("/market/shop/orders/{$second->id}/move", ['to' => $to, 'eta' => 'Saturday 10:00'])->assertSessionHasNoErrors();
    }
    expect($second->refresh()->status)->toBe('completed')->and($second->closed_at)->not->toBeNull();
});

it('lets buyers cancel only before the trader accepts', function (): void {
    $order = cartAndOrder($this);
    $this->post("/market/orders/{$order->id}/cancel")->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('cancelled')->and($this->oil->refresh()->stock)->toBe(10);

    $second = cartAndOrder($this);
    app(Ordering::class)->move($second, $this->trader, 'accepted');
    Staff::signIn($this, $this->buyer);
    $this->post("/market/orders/{$second->id}/cancel")->assertSessionHasErrors('order');
});

it('reminds traders at 24 hours and lapses orders at 48 hours, giving stock back', function (): void {
    $order = cartAndOrder($this);
    $this->travel(25)->hours();
    expect(app(Ordering::class)->sweep())->toBe(['reminded' => 1, 'lapsed' => 0]);
    $this->travel(24)->hours();
    expect(app(Ordering::class)->sweep()['lapsed'])->toBe(1)
        ->and($order->refresh()->status)->toBe('lapsed')->and($this->oil->refresh()->stock)->toBe(10);
});

it('tracks WhatsApp orders and shares the buyer\'s phone with the trader only by choice', function (): void {
    $order = cartAndOrder($this, ['channel' => 'whatsapp', 'share_phone' => false]);
    expect($order->channel)->toBe('whatsapp')->and($order->number)->toStartWith('KM-');
    Staff::signIn($this, $this->trader);
    $this->get("/market/shop/orders/{$order->id}")->assertInertia(fn ($p) => $p->where('buyer.phone', null)->where('buyer.name', 'Thandeka'));

    $shared = cartAndOrder($this, ['share_phone' => true]);
    Staff::signIn($this, $this->trader);
    $this->get("/market/shop/orders/{$shared->id}")->assertInertia(fn ($p) => $p->where('buyer.phone', '+27725550404'));

    Staff::signIn($this, User::factory()->create());
    $this->get("/market/orders/{$order->id}")->assertNotFound();
});

it('moderates order messages and counts completed orders and their value', function (): void {
    $order = cartAndOrder($this);
    $this->post("/market/orders/{$order->id}/messages", ['body' => 'Please deliver after 5pm'])->assertSessionHasNoErrors();
    expect(DB::table('market_order_messages')->value('status'))->toBe('sent');

    foreach (['accepted', 'out_for_delivery', 'completed'] as $to) {
        app(Ordering::class)->move($order->refresh(), $this->trader, $to);
    }
    $totals = app(Insights::class)->totals(null, now()->subDay()->toImmutable(), now()->addDay()->toImmutable(), ['market.orders_completed', 'market.order_value']);
    expect($totals['market.orders_completed']['total'])->toBe(1)->and($totals['market.order_value']['total'])->toBe(46000);
});

it('lets a hub facilitator place an order for someone in an assisted session', function (): void {
    $facilitator = Structure::personWith('hub_facilitator', Scope::hub(Structure::hub('LP-GIY-TSU')));
    Staff::signIn($this, $facilitator);
    app(AssistedSession::class)->start($facilitator, $this->buyer, Structure::hub('LP-GIY-TSU'));
    $this->post("/market/cart/{$this->braids->id}", ['quantity' => 1]);
    $this->post("/market/cart/shops/{$this->shop->id}/order", ['fulfilment' => 'collection', 'channel' => 'app'])->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->buyer_id)->toBe($this->buyer->id)->and($order->placed_by)->toBe($facilitator->id);
});

it('tells the buyer in their own words when the trader cancels', function (): void {
    $order = cartAndOrder($this);
    app(Ordering::class)->move($order, $this->trader, 'accepted');
    app(Ordering::class)->move($order->refresh(), $this->trader, 'cancelled', 'Fell ill, sorry');
    $update = Update::query()->where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
    expect((string) $update->title)->toContain('was cancelled')->and((string) $update->body)->not->toContain('The buyer cancelled');
});
