<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Biz\Models\Entry;
use Modules\Biz\Services\BizSignals;
use Modules\Biz\Services\Books;
use Modules\Biz\Services\Owed;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Tests\Structure;
use Modules\Market\Models\Order;
use Modules\Market\Models\Shop;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(StepSeeder::class);
    $this->owner = User::factory()->create(['date_of_birth' => now()->subYears(30)]);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->books = app(Books::class);
});

function entry(TestCase $t, string $dir, int $cents, string $cat, string $method = 'cash', ?string $on = null): Entry
{
    return $t->books->record($t->business->id, $t->owner, ['direction' => $dir, 'amount_cents' => $cents, 'category' => $cat, 'method' => $method, 'occurred_on' => $on]);
}

it('counts trading money in profit, and loans and the owner\'s money only in cash', function (): void {
    $this->books->setOpening($this->business->id, ['cash' => 30000], $this->owner);
    entry($this, 'in', 50000, 'sales');
    entry($this, 'out', 20000, 'stock_materials');
    entry($this, 'in', 100000, 'loan_received', 'card');
    entry($this, 'out', 5000, 'owner_took_out');
    $s = $this->books->summary($this->business->id, now()->format('Y-m'));
    expect($s['in'])->toBe(50000)->and($s['out'])->toBe(20000)->and($s['profit'])->toBe(30000)
        ->and($s['cash'])->toBe(['cash' => 30000 + 50000 - 20000 - 5000, 'card' => 100000, 'mobile' => 0])
        ->and($s['top_costs'][0])->toBe(['category' => 'stock_materials', 'cents' => 20000]);
});

it('allows corrections for 30 days and keeps every change', function (): void {
    $e = entry($this, 'out', 1000, 'transport');
    $this->books->edit($e, $this->owner, ['direction' => 'out', 'amount_cents' => 1500, 'category' => 'transport', 'method' => 'cash']);
    $this->books->remove($e->refresh(), $this->owner);
    expect(DB::table('biz_entry_changes')->where('entry_id', $e->id)->pluck('action')->all())->toBe(['edited', 'removed'])
        ->and(Entry::withTrashed()->find($e->id)?->amount_cents)->toBe(1500);

    $old = entry($this, 'out', 1000, 'rent');
    $this->travel(31)->days();
    expect(fn () => $this->books->remove($old->refresh(), $this->owner))->toThrow(DomainException::class)
        ->and(fn () => entry($this, 'in', 1000, 'stock_materials'))->toThrow(DomainException::class); // wrong category for money in
});

it('imports KasiMarket sales once and keeps a refund as one entry that follows the total refunded', function (): void {
    $shop = Shop::query()->create(['business_id' => $this->business->id, 'slug' => 'nomsa', 'name' => 'Nomsa', 'status' => 'open', 'away' => false, 'approved_products' => 3, 'opened_by' => $this->owner->id]);
    $buyer = User::factory()->create();
    $order = Order::query()->create(['number' => 'KM-2026-000001', 'shop_id' => $shop->id, 'buyer_id' => $buyer->id, 'channel' => 'app', 'fulfilment' => 'collection',
        'items_cents' => 10000, 'delivery_fee_cents' => 0, 'total_cents' => 10000, 'status' => 'completed', 'completed_at' => now(), 'payment' => 'online', 'payment_status' => 'part_refunded',
        'refunded_cents' => 2000]);
    expect($this->books->sync($this->business->id, $this->owner))->toBe(2)->and($this->books->sync($this->business->id, $this->owner))->toBe(0);
    $order->forceFill(['refunded_cents' => 5000])->save();
    $this->books->sync($this->business->id, $this->owner);
    expect(Entry::query()->where('source_ref', 'market:refund:KM-2026-000001')->count())->toBe(1)
        ->and(Entry::query()->where('source_ref', 'market:refund:KM-2026-000001')->value('amount_cents'))->toBe(5000)
        ->and(fn () => $this->books->edit(Entry::query()->where('source_ref', 'market:sale:KM-2026-000001')->firstOrFail(), $this->owner,
            ['direction' => 'in', 'amount_cents' => 1, 'category' => 'sales', 'method' => 'cash']))->toThrow(DomainException::class);
});

it('tracks money owed and writes the cash book entry when it is paid', function (): void {
    $owed = app(Owed::class);
    $customer = $owed->contact($this->business->id, 'customer', 'Gogo Dlamini', '072 555 0101', null);
    $credit = $owed->add($this->business->id, $customer, 'owed_me', 15000, 'Braids on credit', null, $this->owner);
    expect(Owed::open($this->business->id)['owed_me'][0]['cents'])->toBe(15000);
    $owed->paid($this->business->id, $credit, 'mobile', $this->owner);
    expect(Owed::open($this->business->id)['owed_me'])->toBe([])
        ->and($this->books->summary($this->business->id, now()->format('Y-m'))['cash']['mobile'])->toBe(15000);
});

it('tells partners only whether records are kept and a turnover band', function (): void {
    foreach ([3, 2, 1] as $ago) {
        entry($this, 'in', 900000, 'sales', 'cash', now()->subMonthsNoOverflow($ago)->toDateString());
    }
    $signals = (new BizSignals)->signals($this->business->id);
    expect($signals)->toBe(['biz.keeps_records' => true, 'biz.turnover_band' => '5k_20k'])
        ->and(app(BusinessFacts::class)->facts($this->business->id)['signals'])->toHaveKey('biz.keeps_records');
});
