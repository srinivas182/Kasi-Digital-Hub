<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Commercial\Database\Seeders\CatalogueSeeder;
use Modules\Commercial\Models\LedgerEntry;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Payments\FakeGateway;
use Modules\Commercial\Services\Catalogue;
use Modules\Commercial\Services\HubFinance;
use Modules\Commercial\Services\Invoicing;
use Modules\Commercial\Services\Settlement;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Services\AdvertSlots;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(CatalogueSeeder::class);
    $this->hub = Structure::hub('LP-GIY-TSU'); // a local municipality: rural tier
    $this->owner = Structure::personWith('hub_owner', Scope::hub($this->hub));
    $this->employer = Organisation::query()->create(['type' => 'employer', 'name' => 'Mopani Fresh', 'verification_status' => 'verified']);
    $this->admin = Structure::personWith('employer_admin', Scope::organisation($this->employer));
    $this->admin->forceFill(['home_hub_id' => $this->hub->id])->save();
    $this->commercial = Structure::personWith('commercial_admin', Scope::national());
    $this->finance = Structure::personWith('finance_admin', Scope::national());
    $this->finance2 = Structure::personWith('finance_admin', Scope::national());
});

function readyHub(TestCase $t, bool $vat = false): void
{
    HubFinance::upsert($t->hub->id, ['company_registration' => '2020/123456/07', 'vat_registered' => $vat, 'vat_number' => $vat ? '4123456789' : null,
        'bank_verified' => true, 'franchise_version' => config('kasi.commercial.franchise_version'), 'provider_subaccount' => 'ACCT_hub']);
}

function payOnline(TestCase $t, int $quantity = 1): Order
{
    Staff::signIn($t, $t->admin);
    $t->post('/commercial/buy/work_extra_advert', ['organisation' => $t->employer->id, 'quantity' => $quantity])->assertSessionHasNoErrors(); // redirected to the payment page
    $order = Order::query()->latest('created_at')->firstOrFail();
    $t->post('/commercial/fake-pay/'.$order->provider_ref, ['outcome' => 'pay'])->assertRedirect();

    return $order->refresh();
}

it('sells an extra advert online, attributed to the buyer\'s hub, split exactly, and delivers the slot', function (): void {
    readyHub($this);
    $order = payOnline($this);

    expect($order->status)->toBe('paid')->and($order->attribution)->toBe('nearest')->and($order->hub_id)->toBe($this->hub->id)
        ->and($order->total_cents)->toBe(15000)->and($order->vat_cents)->toBe(0) // rural price, hub not VAT-registered
        ->and($order->shares)->toEqual(['kasi' => 9000, 'hub' => 6000, 'partner' => 0]) // content, not key order (MySQL re-orders JSON keys)
        ->and((int) LedgerEntry::query()->where('order_id', $order->id)->sum('amount_cents'))->toBe(15000)
        ->and(AdvertSlots::limit($this->employer))->toBe((int) config('kasi.work.free_listing_limit') + 1);
});

it('charges VAT only for VAT-registered merchants and keeps it out of the shares', function (): void {
    readyHub($this, vat: true);
    $order = payOnline($this);

    expect($order->vat_cents)->toBe(1957)->and(array_sum($order->shares))->toBe(15000 - 1957)
        ->and((int) LedgerEntry::query()->where('order_id', $order->id)->where('account', 'vat')->sum('amount_cents'))->toBe(1957);
});

it('sends the sale national when the hub is not ready to sell', function (): void {
    $order = payOnline($this);
    expect($order->hub_id)->toBeNull()->and($order->attribution)->toBe('national')->and($order->merchant)->toBe('kasi')->and($order->shares['hub'])->toBe(0);
});

it('applies payment notifications once, rejects bad signatures, and never double-counts', function (): void {
    readyHub($this);
    $order = payOnline($this);
    $body = (string) json_encode(['id' => 'fake-'.$order->provider_ref.'-ok', 'event' => 'charge.success', 'reference' => $order->provider_ref, 'amount' => $order->total_cents, 'status' => 'success']);

    $this->call('POST', '/commercial/webhooks/payments', [], [], [], ['HTTP_X_FAKE_SIGNATURE' => FakeGateway::sign($body), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
    $this->call('POST', '/commercial/webhooks/payments', [], [], [], ['HTTP_X_FAKE_SIGNATURE' => 'wrong', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
    expect(LedgerEntry::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and(DB::table('work_advert_slots')->count())->toBe(1);
});

it('refunds proportionally, reverses the ledger and takes back the slot on a full refund', function (): void {
    readyHub($this);
    $order = payOnline($this);
    Staff::signIn($this, $this->finance);
    $this->post("/commercial/orders/{$order->id}/refund", ['amount' => 5000, 'reason' => 'Advert not needed'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('part_refunded')->and((int) LedgerEntry::query()->where('order_id', $order->id)->sum('amount_cents'))->toBe(10000);

    $this->post("/commercial/orders/{$order->id}/refund", ['amount' => 10000, 'reason' => 'Rest'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('refunded')->and((int) LedgerEntry::query()->where('order_id', $order->id)->sum('amount_cents'))->toBe(0)
        ->and(DB::table('work_advert_slots')->count())->toBe(0);
    $this->post("/commercial/orders/{$order->id}/refund", ['amount' => 1, 'reason' => 'More'])->assertSessionHasErrors('commercial');
});

it('keeps ledger entries immutable', function (): void {
    readyHub($this);
    payOnline($this);
    $entry = LedgerEntry::query()->firstOrFail();
    expect(fn () => $entry->update(['amount_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('sells at the hub point of sale within the price band and nets the royalty against the payout', function (): void {
    readyHub($this);
    Staff::signIn($this, $this->owner);
    $this->post('/commercial/hub/sell', ['product' => 'onsite_desk_day', 'quantity' => 2, 'price' => 1000])->assertSessionHasErrors('sale'); // below the floor
    $this->post('/commercial/hub/sell', ['product' => 'onsite_desk_day', 'quantity' => 2, 'buyer' => 'Walk-in'])->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->channel)->toBe('pos')->and($order->total_cents)->toBe(12000)->and($order->shares)->toEqual(['kasi' => 1200, 'hub' => 10800, 'partner' => 0]);
    $statement = app(Settlement::class)->statement($this->hub, now()->format('Y-m'));
    expect($statement['royalty_due'])->toBe(-1200)->and($statement['balance'])->toBe(-1200);

    app(Settlement::class)->payouts(now()->format('Y-m'));
    expect(DB::table('com_payouts')->value('status'))->toBe('carried');
});

it('needs four eyes for prices and bank accounts', function (): void {
    $product = Product::query()->where('key', 'work_extra_advert')->firstOrFail();
    Staff::signIn($this, $this->commercial);
    $this->post('/commercial/prices', ['product' => $product->id, 'tier' => 'rural', 'price' => 18000, 'floor' => 15000, 'ceiling' => 20000, 'effective_from' => now()->toDateString()])->assertSessionHasNoErrors();
    $approval = DB::table('com_approvals')->value('id');
    $this->post("/commercial/approvals/{$approval}", ['approve' => true])->assertSessionHasErrors('commercial'); // not your own

    $second = Structure::personWith('commercial_admin', Scope::national());
    Staff::signIn($this, $second);
    $this->post("/commercial/approvals/{$approval}", ['approve' => true])->assertSessionHasNoErrors();
    expect(app(Catalogue::class)->price($product, 'rural')['price'])->toBe(18000);

    // Bank account: requested by the hub owner, approved by finance; verified only with a verified letter.
    $letter = Document::query()->create(['user_id' => $this->owner->id, 'type' => 'bank_confirmation', 'disk' => 'documents', 'path' => 'b.pdf', 'original_name' => 'b.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('e', 64), 'status' => 'verified']);
    $id = app(HubFinance::class)->requestBank($this->hub, $letter, 'FNB ...4417', 'ACCT_x', $this->owner);
    Staff::signIn($this, $this->finance);
    $this->post("/commercial/approvals/{$id}", ['approve' => true])->assertSessionHasNoErrors();
    expect((bool) DB::table('com_hub_finance')->where('hub_id', $this->hub->id)->value('bank_verified'))->toBeTrue();
});

it('invoices hub packages, reminds, blocks new paid sales after 30 days, and unblocks on payment', function (): void {
    readyHub($this);
    $this->hub->forceFill(['status' => 'live', 'package' => 'full'])->save();
    expect(app(Invoicing::class)->packages(now()->format('Y-m')))->toBeGreaterThanOrEqual(1)->and(app(Invoicing::class)->packages(now()->format('Y-m')))->toBe(0);
    DB::table('com_invoices')->where('hub_id', '!=', $this->hub->id)->delete(); // other live demo hubs

    $this->travel(8)->days();
    expect(app(Invoicing::class)->chase()['reminders'])->toBe(1);
    $this->travel(23)->days();
    app(Invoicing::class)->chase();
    expect(app(HubFinance::class)->canSell($this->hub))->toBeFalse();

    // Youth services are untouched: KasiWork still works for young people at this hub.
    expect(HubModule::query()->where('hub_id', $this->hub->id)->where('enabled', true)->count())->toBeGreaterThan(0);

    app(Invoicing::class)->markPaid((string) DB::table('com_invoices')->where('hub_id', $this->hub->id)->value('id'), $this->finance);
    expect(app(HubFinance::class)->canSell($this->hub))->toBeTrue()
        ->and((int) LedgerEntry::query()->whereNotNull('invoice_id')->sum('amount_cents'))->toBe((int) DB::table('com_invoices')->where('hub_id', $this->hub->id)->value('amount_cents'));
});

it('reconciles against the provider and flags differences', function (): void {
    readyHub($this);
    $order = payOnline($this);
    Cache::put('fake-settlements:'.now()->toDateString(), [['reference' => $order->provider_ref, 'amount' => $order->total_cents - 100], ['reference' => 'UNKNOWN', 'amount' => 500]]);

    expect(app(Settlement::class)->reconcile(now()->toDateString()))->toBe(2)
        ->and(DB::table('com_reconciliation_issues')->pluck('issue')->sort()->values()->all())->toBe(['amount_differs', 'missing_in_ledger']);
});

it('keeps the console and hub finance to the right people', function (): void {
    Staff::signIn($this, User::factory()->create());
    $this->get('/commercial')->assertForbidden();
    $this->get('/commercial/hub')->assertForbidden();
    Staff::signIn($this, $this->owner);
    $this->get('/commercial/hub')->assertOk();
    $this->post('/commercial/hub/details', ['company_registration' => 'x'])->assertSessionHasNoErrors();
    Staff::signIn($this, $this->commercial);
    $this->get('/commercial')->assertOk();
    $this->post('/commercial/invoices/abc/paid')->assertForbidden(); // finance only
});
