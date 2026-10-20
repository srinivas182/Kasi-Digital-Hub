<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Commercial\Database\Seeders\CatalogueSeeder;
use Modules\Core\Access\Scope;
use Modules\Core\Commerce\Payments;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\Market\Database\Seeders\MarketCategorySeeder;
use Modules\Market\Models\Order;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Disputes;
use Modules\Market\Services\DisputesWaiting;
use Modules\Market\Services\Insights;
use Modules\Market\Services\Ordering;
use Modules\Market\Services\Reviews;
use Modules\Market\Services\Trust;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(MarketCategorySeeder::class);
    $this->seed(CatalogueSeeder::class);
    $this->seed(StepSeeder::class);
    $hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $hub->id, 'module' => 'Market'], ['enabled' => true]);
    $this->trader = User::factory()->create(['home_hub_id' => $hub->id, 'date_of_birth' => now()->subYears(30)]);
    $this->business = app(Businesses::class)->create($this->trader, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->shop = Shop::query()->create(['business_id' => $this->business->id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3,
        'hub_id' => $hub->id, 'opened_by' => $this->trader->id, 'offers_collection' => true, 'accepts_cash' => true, 'accepts_online' => true, 'bank_verified' => true, 'payout_subaccount' => 'ACCT']);
    $this->oil = Product::query()->create(['shop_id' => $this->shop->id, 'category_id' => (int) DB::table('market_categories')->value('id'), 'title' => 'Hair oil', 'price_cents' => 10000,
        'unit' => 'each', 'stock_type' => 'quantity', 'stock' => 50, 'status' => 'published', 'views' => 40]);
    $this->buyer = User::factory()->create(['first_name' => 'Thandeka', 'last_name' => 'Mokoena', 'date_of_birth' => now()->subYears(25)]);
});

function completedOrder(TestCase $t, string $payment = 'cash', ?User $buyer = null): Order
{
    $buyer ??= $t->buyer;
    DB::table('market_cart_items')->insert(['user_id' => $buyer->id, 'product_id' => $t->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $o = app(Ordering::class)->place($buyer, $t->shop, 'collection', null, null, 'app', null, $payment);
    if ($payment === 'online') {
        app(Ordering::class)->paid($o->id); // as the payment notification would
    }
    foreach (['accepted', 'ready', 'completed'] as $to) {
        app(Ordering::class)->move($o->refresh(), $t->trader, $to);
    }

    return $o->refresh();
}

it('lets only the buyer of a completed order review once, shows first name and initial, and a rating only from 3 reviews', function (): void {
    $order = completedOrder($this);
    app(Reviews::class)->add($order, $this->buyer, 5, 'Lovely oil');
    expect(fn () => app(Reviews::class)->add($order, $this->buyer, 1, 'again'))->toThrow(DomainException::class)
        ->and(app(Reviews::class)->canReview($order, User::factory()->create()))->toBeFalse()
        ->and(Reviews::rating($this->shop->id))->toBeNull()
        ->and(Reviews::shown($this->shop->id)[0]['by'])->toBe('Thandeka M.');

    foreach ([4, 3] as $stars) {
        $other = User::factory()->create(['date_of_birth' => now()->subYears(30)]);
        app(Reviews::class)->add(completedOrder($this, 'cash', $other), $other, $stars, null);
    }
    expect(Reviews::rating($this->shop->id))->toBe(4.0);

    $id = (int) DB::table('market_reviews')->value('id');
    app(Reviews::class)->reply($id, $this->trader, 'Thank you!');
    expect(fn () => app(Reviews::class)->reply($id, $this->trader, 'Again'))->toThrow(DomainException::class);
    $this->travel(31)->days();
    expect(app(Reviews::class)->canReview(completedOrder($this), $this->buyer))->toBeTrue(); // a fresh order is fine
});

it('sends KasiMarket facts to partner eligibility through business signals', function (): void {
    completedOrder($this);
    $facts = app(BusinessFacts::class)->facts($this->business->id);
    expect($facts['signals'])->toMatchArray(['market.has_shop' => true, 'market.orders_6m' => 1, 'market.rating' => null]);
});

it('switches a buyer to online payment only after 3 no-shows in 90 days', function (): void {
    foreach (range(1, 3) as $n) {
        DB::table('market_cart_items')->insert(['user_id' => $this->buyer->id, 'product_id' => $this->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $o = app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app');
        app(Ordering::class)->move($o, $this->trader, 'accepted');
        app(Trust::class)->noShow($o->refresh(), $this->trader);
    }
    expect(Trust::onlineOnly($this->buyer->id))->toBeTrue();
    DB::table('market_cart_items')->insert(['user_id' => $this->buyer->id, 'product_id' => $this->oil->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    expect(fn () => app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app'))->toThrow(DomainException::class)
        ->and(app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app', null, 'online')->payment)->toBe('online');
});

it('resolves disputes: refunds only for online payments, mediation for cash, shown in the review inbox', function (): void {
    $cash = completedOrder($this);
    $staff = Structure::personWith('support_agent', Scope::national());
    $id = app(Disputes::class)->open($cash, $this->buyer, 'damaged', 'The bottle was broken');
    expect(collect(app(DisputesWaiting::class)->waiting())->pluck('id'))->toContain($id)
        ->and(fn () => app(Disputes::class)->decide($id, $cash, $staff, 'refund_full', null, 'x'))->toThrow(DomainException::class);
    app(Disputes::class)->decide($id, $cash, $staff, 'mediated', null, 'Trader agreed to replace it');
    expect(DB::table('market_disputes')->where('id', $id)->value('status'))->toBe('resolved');

    $paid = completedOrder($this, 'online');
    // (no real payment reference in this test - the refund goes through the Payments contract)
    $paid->forceFill(['payment_ref' => null])->save();
    $this->mock(Payments::class)->shouldReceive('refundSale')->once();
    $id2 = app(Disputes::class)->open($paid, $this->buyer, 'not_described', 'Wrong colour');
    app(Disputes::class)->decide($id2, $paid->refresh(), $staff, 'refund_part', 4000, 'Half refunded');
    expect($paid->refresh()->payment_status)->toBe('part_refunded')->and((int) $paid->refunded_cents)->toBe(4000);
});

it('suspends a shop only at 3 active strikes, cancelling open orders, and lets moderators restore it', function (): void {
    $moderator = Structure::personWith('content_reviewer', Scope::national());
    DB::table('market_cart_items')->insert(['user_id' => $this->buyer->id, 'product_id' => $this->oil->id, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()]);
    $open = app(Ordering::class)->place($this->buyer, $this->shop, 'collection', null, null, 'app');
    app(Trust::class)->strike($this->shop, $moderator, 'Counterfeit goods', 'report');
    expect(fn () => app(Trust::class)->suspend($this->shop, $moderator, 'Repeated'))->toThrow(DomainException::class);
    app(Trust::class)->strike($this->shop, $moderator, 'Rude to buyers', 'dispute');
    app(Trust::class)->strike($this->shop, $moderator, 'No-shows', 'other');
    app(Trust::class)->suspend($this->shop, $moderator, 'Three strikes');
    expect($this->shop->refresh()->status)->toBe('suspended')->and($open->refresh()->status)->toBe('cancelled')->and($this->oil->refresh()->stock)->toBe(50);
    $this->get('/market/shops/nomsa')->assertNotFound();

    $this->travel(181)->days();
    expect(Trust::activeStrikes($this->shop))->toBe(0);
    app(Trust::class)->lift($this->shop, $moderator, 'Appeal accepted');
    expect($this->shop->refresh()->status)->toBe('open');
});

it('gives the trader insights from completed orders', function (): void {
    completedOrder($this);
    completedOrder($this, 'online');
    $i = Insights::for($this->shop, 30);
    expect($i['orders'])->toBe(2)->and($i['repeat_buyers'])->toBe(1)->and($i['top'][0]['quantity'])->toBe(2)
        ->and($i['cash_cents'])->toBe(10000)->and($i['online_cents'])->toBe(10000)->and($i['conversion'])->toBe(5.0);
});
