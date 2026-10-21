<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Biz\Services\Books;
use Modules\Biz\Services\Compliance;
use Modules\Biz\Services\Stock;
use Modules\Biz\Services\Summaries;
use Modules\Core\Identity\Models\User;
use Modules\Core\Tests\Structure;
use Modules\Market\Database\Seeders\MarketCategorySeeder;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(StepSeeder::class);
    $this->owner = User::factory()->create(['date_of_birth' => now()->subYears(30)]);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->stock = app(Stock::class);
});

it('keeps stock with history: buying adds, using takes away, a stock-take records the difference', function (): void {
    $id = $this->stock->add($this->business->id, $this->owner, ['name' => 'Hair oil', 'quantity' => 5, 'reorder_level' => 3, 'cost_cents' => 2000]);
    $this->stock->move($this->business->id, $id, $this->owner, 'bought', 10, 15000, null, toBooks: true);
    $this->stock->move($this->business->id, $id, $this->owner, 'used', 2);
    expect($this->stock->move($this->business->id, $id, $this->owner, 'counted', 11))->toBe(11);

    $history = $this->stock->history($this->business->id, $id);
    expect(array_column($history, 'kind'))->toBe(['counted', 'used', 'bought'])->and($history[0]['change'])->toBe(-2) // 13 expected, 11 counted
        ->and(DB::table('biz_entries')->where('category', 'stock_materials')->value('amount_cents'))->toBe(15000) // "bought" went into the cash book once
        ->and((int) DB::table('biz_stock_items')->where('id', $id)->value('cost_cents'))->toBe(1500);
});

it('shares one quantity with a linked KasiMarket product', function (): void {
    $shop = Shop::query()->create(['business_id' => $this->business->id, 'slug' => 'nomsa', 'name' => 'Nomsa', 'status' => 'open', 'away' => false, 'approved_products' => 3, 'opened_by' => $this->owner->id]);
    $this->seed(MarketCategorySeeder::class);
    $oil = Product::query()->create(['shop_id' => $shop->id, 'category_id' => (int) DB::table('market_categories')->value('id'), 'title' => 'Hair oil', 'price_cents' => 4500,
        'unit' => 'each', 'stock_type' => 'quantity', 'stock' => 8, 'status' => 'published', 'views' => 0]);
    $id = $this->stock->add($this->business->id, $this->owner, ['name' => 'Hair oil', 'market_product_id' => $oil->id, 'reorder_level' => 5]);

    $this->stock->move($this->business->id, $id, $this->owner, 'bought', 4);
    expect($oil->refresh()->stock)->toBe(12);
    $oil->forceFill(['stock' => 3])->save(); // e.g. orders on KasiMarket
    expect($this->stock->items($this->business->id)[0])->toMatchArray(['quantity' => 3, 'low' => true, 'linked' => true]);
});

it('announces low stock at most once a day', function (): void {
    $this->stock->add($this->business->id, $this->owner, ['name' => 'Gel', 'quantity' => 1, 'reorder_level' => 2]);
    expect($this->stock->takeNewLow($this->business->id))->toBe(['Gel'])->and($this->stock->takeNewLow($this->business->id))->toBe([]);
    $this->travel(1)->days();
    expect($this->stock->takeNewLow($this->business->id))->toBe(['Gel']);
});

it('works out compliance dates from the facts and the confirmed profile', function (): void {
    $c = app(Compliance::class);
    expect($c->schedule($this->business->id, CarbonImmutable::parse('2026-11-01')))->toBe([]); // informal, nothing set

    $this->business->forceFill(['legal_form' => 'pty'])->save();
    $c->saveProfile($this->business->id, $this->owner, ['registered_on' => '2024-03-15', 'turnover_tax' => true, 'employs' => true, 'vat_registered' => false]);
    $rules = collect($c->schedule($this->business->id, CarbonImmutable::parse('2026-11-01')))->groupBy('rule');
    expect($rules->keys()->sort()->values()->all())->toBe(['cipc_annual', 'coida', 'emp201', 'turnover_annual', 'turnover_interim'])
        ->and($rules['cipc_annual']->pluck('due_on'))->toContain('2027-04-26') // 30 business days after 15 March 2027
        ->and($rules['turnover_interim']->pluck('due_on'))->toContain('2027-02-26') // 28 Feb 2027 is a Sunday -> Friday 26th
        ->and($rules['emp201']->pluck('due_on'))->toContain('2027-02-05'); // 7 Feb 2027 is a Sunday -> Friday 5th
});

it('marks obligations done, lets the owner move a date, and knows when the business is up to date', function (): void {
    $c = app(Compliance::class);
    $c->saveProfile($this->business->id, $this->owner, ['employs' => true]);
    $this->travel(40)->days(); // some EMP201 dates are now in the past
    expect(Compliance::upToDate($this->business->id))->toBeFalse();
    foreach (DB::table('biz_obligations')->where('due_on', '<', now()->toDateString())->pluck('id') as $id) {
        $c->done($this->business->id, (int) $id, $this->owner, null);
    }
    expect(Compliance::upToDate($this->business->id))->toBeTrue();
    $next = (int) DB::table('biz_obligations')->where('status', 'due')->orderBy('due_on')->value('id');
    $c->moveDate($this->business->id, $next, $this->owner, now()->addDays(20)->toDateString());
    expect((bool) DB::table('biz_obligations')->where('id', $next)->value('adjusted'))->toBeTrue();
});

it('reminds at 14 and 3 days, weekly when overdue, and sends one low-stock message', function (): void {
    app(Compliance::class)->saveProfile($this->business->id, $this->owner, ['bbbee_on' => now()->addDays(10)->setYear(now()->year - 1)->toDateString()]);
    $this->stock->add($this->business->id, $this->owner, ['name' => 'Gel', 'quantity' => 0, 'reorder_level' => 1]);
    $this->artisan('kasi:biz:daily')->assertSuccessful();
    $titles = DB::table('updates')->where('user_id', $this->owner->id)->pluck('title')->implode(' | ');
    expect($titles)->toContain('B-BBEE')->toContain('Low stock');

    $this->travel(8)->days();
    $this->artisan('kasi:biz:daily')->assertSuccessful(); // 2 days before: the 3-day reminder
    $this->travel(5)->days();
    $this->artisan('kasi:biz:daily')->assertSuccessful(); // overdue
    expect(DB::table('biz_obligations')->where('rule', 'bbbee')->whereNotNull('reminded_14')->whereNotNull('reminded_3')->whereNotNull('reminded_overdue')->exists())->toBeTrue();
});

it('shares a summary with bands only, by a 7-day link that can be withdrawn, logging every view', function (): void {
    app(Books::class)->record($this->business->id, $this->owner, ['direction' => 'in', 'amount_cents' => 1234567, 'category' => 'sales', 'method' => 'cash',
        'occurred_on' => now()->subMonth()->toDateString()]);
    $summaries = app(Summaries::class);
    $built = $summaries->build($this->business->id, 'Nomsa Hair');
    expect(json_encode($built))->not->toContain('1234567')->not->toContain('12345')
        ->and($built['months_recorded'])->toBe(1)->and($built['profitable_months'])->toBe(1);

    $token = $summaries->share($this->business->id, $this->owner, 'Ubuntu Community Bank');
    $this->get('/biz/shared/'.$token)->assertOk();
    expect(DB::table('biz_share_views')->count())->toBe(1);
    $summaries->withdraw($this->business->id, (string) DB::table('biz_shares')->value('id'), $this->owner);
    $this->get('/biz/shared/'.$token)->assertStatus(410);

    $late = $summaries->share($this->business->id, $this->owner, null);
    $this->travel(8)->days();
    $this->get('/biz/shared/'.$late)->assertStatus(410);
});
