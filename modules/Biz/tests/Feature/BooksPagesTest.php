<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Biz\Models\Entry;
use Modules\Core\Access\Scope;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(StepSeeder::class);
    $this->hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $this->hub->id, 'module' => 'Biz'], ['enabled' => true]);
    $this->owner = User::factory()->create(['home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(30), 'last_name' => 'Owner-Zwide']);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
});

it('lets the owner record money and see the month, and shows others nothing', function (): void {
    Staff::signIn($this, $this->owner);
    $this->post('/biz/entries', ['direction' => 'in', 'amount_cents' => 25000, 'category' => 'sales', 'method' => 'cash'])->assertSessionHasNoErrors();
    $this->get('/biz')->assertOk()->assertInertia(fn ($p) => $p->component('Biz/Books')->where('summary.in', 25000)->has('entries', 1));

    $entry = Entry::query()->sole();
    Staff::signIn($this, User::factory()->create(['home_hub_id' => $this->hub->id]));
    $this->get('/biz')->assertInertia(fn ($p) => $p->component('Biz/Start')->where('hasBusiness', false));
    $this->delete("/biz/entries/{$entry->id}")->assertNotFound();
    $this->get('/biz/export.csv')->assertNotFound();
});

it('is available only where the hub has KasiBiz', function (): void {
    HubModule::query()->where('hub_id', $this->hub->id)->where('module', 'Biz')->update(['enabled' => false]);
    Staff::signIn($this, $this->owner);
    $this->get('/biz')->assertInertia(fn ($p) => $p->component('Biz/Start')->where('hasBusiness', true));
    $this->post('/biz/entries', ['direction' => 'in', 'amount_cents' => 100, 'category' => 'sales', 'method' => 'cash'])->assertNotFound();
});

it('records in the helped person\'s books during a hub assisted session', function (): void {
    $facilitator = Structure::personWith('hub_facilitator', Scope::hub($this->hub));
    Staff::signIn($this, $facilitator);
    app(AssistedSession::class)->start($facilitator, $this->owner, $this->hub);
    $this->post('/biz/entries', ['direction' => 'out', 'amount_cents' => 4000, 'category' => 'transport', 'method' => 'cash'])->assertSessionHasNoErrors();
    expect(Entry::query()->sole()->business_id)->toBe($this->business->id)->and(Entry::query()->sole()->created_by)->toBe($facilitator->id);
});

it('downloads the month as CSV and a summary PDF', function (): void {
    Staff::signIn($this, $this->owner);
    $this->post('/biz/entries', ['direction' => 'in', 'amount_cents' => 25000, 'category' => 'sales', 'method' => 'cash', 'note' => 'Braids']);
    $csv = $this->get('/biz/export.csv')->assertOk()->streamedContent();
    expect($csv)->toContain('date,money,amount_rand,category')->toContain('250.00,sales,cash,Braids');
    $this->get('/biz/summary.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('keeps phone numbers of customers inside the business', function (): void {
    Staff::signIn($this, $this->owner);
    $this->post('/biz/contacts', ['kind' => 'customer', 'name' => 'Gogo Dlamini', 'phone' => '072 555 0101'])->assertSessionHasNoErrors();
    expect(DB::table('biz_contacts')->value('phone'))->toBe('+27725550101');
    Staff::signIn($this, User::factory()->create(['home_hub_id' => $this->hub->id]));
    $this->get('/biz/owed')->assertNotFound();
});
