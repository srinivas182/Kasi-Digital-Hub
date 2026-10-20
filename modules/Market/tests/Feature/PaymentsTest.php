<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Commercial\Database\Seeders\CatalogueSeeder;
use Modules\Commercial\Services\Settlement;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
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
    $this->seed(CatalogueSeeder::class);
    $this->seed(StepSeeder::class);
    $this->hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $this->hub->id, 'module' => 'Market'], ['enabled' => true]);
    $this->trader = User::factory()->create(['first_name' => 'Nomsa', 'home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(30)]);
    $business = app(Businesses::class)->create($this->trader, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->shop = Shop::query()->create(['business_id' => $business->id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3,
        'hub_id' => $this->hub->id, 'opened_by' => $this->trader->id, 'offers_collection' => true, 'offers_delivery' => true, 'delivery_fee_cents' => 2000,
        'accepts_online' => true, 'accepts_cash' => true, 'bank_verified' => true, 'payout_subaccount' => 'ACCT_trader', 'offers_hub_collection' => true]);
    DB::table('market_collection_points')->insert(['hub_id' => $this->hub->id, 'enabled' => true, 'hours' => 'Mon-Sat 08:00-17:00', 'created_at' => now(), 'updated_at' => now()]);
    $this->oil = Product::query()->create(['shop_id' => $this->shop->id, 'category_id' => (int) DB::table('market_categories')->value('id'), 'title' => 'Hair oil', 'price_cents' => 10000,
        'unit' => 'each', 'stock_type' => 'quantity', 'stock' => 10, 'status' => 'published', 'views' => 0]);
    $this->buyer = User::factory()->create(['date_of_birth' => now()->subYears(25)]);
});

function onlineOrder(TestCase $t, string $fulfilment = 'delivery'): Order
{
    DB::table('market_cart_items')->insert(['user_id' => $t->buyer->id, 'product_id' => $t->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);

    return app(Ordering::class)->place($t->buyer, $t->shop, $fulfilment, $fulfilment === 'delivery' ? '1 Main Rd' : null, null, 'app', null, 'online');
}

function pay(TestCase $t, Order $order): void
{
    app(Ordering::class)->startPayment($order->load('shop'), $t->buyer);
    $ref = (string) DB::table('com_orders')->where('id', $order->refresh()->payment_ref)->value('provider_ref');
    Staff::signIn($t, $t->buyer);
    $t->post("/commercial/fake-pay/{$ref}", ['outcome' => 'pay'])->assertRedirect();
}

it('takes online payment with the split at source: trader 92% plus delivery, hub 3%, Kasi 5%', function (): void {
    $order = onlineOrder($this);
    expect($order->payment_status)->toBe('pending')->and($order->total_cents)->toBe(12000);
    pay($this, $order);

    expect($order->refresh()->payment_status)->toBe('paid');
    $sale = DB::table('com_orders')->where('id', $order->payment_ref)->first();
    expect(json_decode((string) $sale->shares, true))->toEqual(['kasi' => 500, 'hub' => 300, 'partner' => 0, 'trader' => 9200 + 2000])
        ->and((int) DB::table('com_ledger')->where('order_id', $sale->id)->where('account', 'trader')->where('payee_ref', 'market:'.$this->shop->id)->sum('amount_cents'))->toBe(11200);
});

it('refunds in full when a paid order is declined, and lets the trader refund part', function (): void {
    $order = onlineOrder($this);
    pay($this, $order);
    app(Ordering::class)->move($order->refresh(), $this->trader, 'declined', 'Out of stock');
    expect($order->refresh()->payment_status)->toBe('refunded')->and((int) $order->refunded_cents)->toBe(12000)
        ->and((int) DB::table('com_ledger')->where('order_id', $order->payment_ref)->sum('amount_cents'))->toBe(0);

    $second = onlineOrder($this);
    pay($this, $second);
    app(Ordering::class)->move($second->refresh(), $this->trader, 'accepted');
    app(Ordering::class)->partialRefund($second->refresh(), $this->trader, 3000, 'One bottle was broken');
    expect($second->refresh()->payment_status)->toBe('part_refunded');
    expect(fn () => app(Ordering::class)->partialRefund($second->refresh(), $this->trader, 99999, 'Too much'))->toThrow(DomainException::class);
});

it('holds stock for 30 minutes while payment is pending, then cancels and gives it back', function (): void {
    $order = onlineOrder($this);
    expect($this->oil->refresh()->stock)->toBe(9);
    $this->travel(31)->minutes();
    expect(app(Ordering::class)->sweep()['unpaid'])->toBe(1)
        ->and($order->refresh()->status)->toBe('cancelled')->and($this->oil->refresh()->stock)->toBe(10);
});

it('needs a verified bank account before taking online payment, and offers cash otherwise', function (): void {
    $this->shop->forceFill(['bank_verified' => false])->save();
    expect(fn () => onlineOrder($this))->toThrow(DomainException::class);
    DB::table('market_cart_items')->delete();
    DB::table('market_cart_items')->insert(['user_id' => $this->buyer->id, 'product_id' => $this->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    expect(app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app')->payment)->toBe('cash');
});

it('works out delivery by zone and gives free delivery over the threshold', function (): void {
    $this->shop->forceFill(['delivery_zones' => json_encode([['name' => 'Giyani town', 'fee_cents' => 3000], ['name' => 'Tsutsumani', 'fee_cents' => 1500]]),
        'free_delivery_over_cents' => 20000])->save();
    expect(Ordering::deliveryFee($this->shop->refresh(), 10000, 'Tsutsumani'))->toBe(1500)
        ->and(Ordering::deliveryFee($this->shop, 25000, 'Giyani town'))->toBe(0)
        ->and(fn () => Ordering::deliveryFee($this->shop, 10000, null))->toThrow(DomainException::class);
});

it('runs hub collection: staff check in, the buyer collects with a code, wrong codes are limited, 7 days returns it', function (): void {
    $order = onlineOrder($this, 'hub');
    pay($this, $order);
    app(Ordering::class)->move($order->refresh(), $this->trader, 'accepted');
    $staff = Structure::personWith('hub_facilitator', Scope::hub($this->hub));
    app(Ordering::class)->checkInAtHub($order->refresh(), $staff);
    expect($order->refresh()->status)->toBe('at_hub');

    // The code reached the buyer in their update; check the stored hash instead of reading it back.
    $order->forceFill(['collection_code_hash' => Hash::make('482913')])->save();
    expect(fn () => app(Ordering::class)->handOver($order->refresh(), $staff, '000000'))->toThrow(DomainException::class);
    app(Ordering::class)->handOver($order->refresh(), $staff, '482913');
    expect($order->refresh()->status)->toBe('completed');

    $late = onlineOrder($this, 'hub');
    pay($this, $late);
    app(Ordering::class)->move($late->refresh(), $this->trader, 'accepted');
    app(Ordering::class)->checkInAtHub($late->refresh(), $staff);
    $this->travel(8)->days();
    expect(app(Ordering::class)->sweep()['uncollected'])->toBe(1)
        ->and($late->refresh()->status)->toBe('uncollected')->and($late->payment_status)->toBe('refunded');
});

it('owes the hub its share of online market sales in the monthly statement', function (): void {
    $order = onlineOrder($this);
    pay($this, $order);
    $statement = app(Settlement::class)->statement($this->hub, now()->format('Y-m'));
    expect($statement['market_shares'])->toBe(300)->and($statement['balance'])->toBe(300);
});
