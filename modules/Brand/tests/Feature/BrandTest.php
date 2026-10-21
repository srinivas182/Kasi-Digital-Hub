<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Brand\Services\Catalogue;
use Modules\Brand\Services\Designs;
use Modules\Brand\Services\Kits;
use Modules\Core\Enterprise\Signals;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Market\Database\Seeders\MarketCategorySeeder;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(StepSeeder::class);
    Storage::fake('public');
    $this->hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $this->hub->id, 'module' => 'Brand'], ['enabled' => true]);
    $this->owner = User::factory()->create(['home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(30)]);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
});

it('draws a text logo as SVG with the text escaped and colours validated', function (): void {
    $svg = Kits::textLogoSvg('<b>&"x', 'badge', '#7A1F5C', 'javascript:alert(1)');
    expect($svg)->toContain('&lt;b&gt;&amp;&quot;x')->not->toContain('<b>')->not->toContain('javascript')
        ->and(simplexml_load_string($svg))->not->toBeFalse() // valid XML
        ->and(Kits::initials('Nomsa Hair Studio'))->toBe('NHS');
});

it('checks contrast like WCAG and suggests a readable shade', function (): void {
    expect(Kits::contrast('#FFFFFF', '#000000'))->toBe(21.0)
        ->and(Kits::readableOn('#1F4E79'))->toBe('#FFFFFF')->and(Kits::readableOn('#F5B700'))->toBe('#000000');
    $shade = Kits::readableShade('#7FB3E5'); // too light for white text
    expect(Kits::contrast($shade, '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)->and(Kits::readableShade('#1F4E79'))->toBe('#1F4E79');
});

it('keeps template fields within their limits and the price list to 20 lines', function (): void {
    $fields = Designs::clean('offer', ['headline' => str_repeat('A', 99), 'text' => 'Box braids', 'until' => 'not-a-date', 'hacker' => 'x']);
    expect(mb_strlen($fields['headline']))->toBe(30)->and($fields['until'])->toBe('')->and($fields)->not->toHaveKey('hacker');
    $list = Designs::clean('price_list', ['items' => implode("\n", array_map(static fn ($i) => "Item {$i} | R{$i}", range(1, 30)))]);
    expect(count(explode("\n", $list['items'])))->toBe(20);
    expect(fn () => Designs::clean('nope', []))->toThrow(DomainException::class);
});

it('saves a kit, uploads a logo without metadata and only for the owner, and lets the hub decide availability', function (): void {
    Staff::signIn($this, $this->owner);
    $this->post('/brand/kit', ['logo_shape' => 'circle', 'logo_text' => 'NH', 'primary' => '#7A1F5C', 'secondary' => '#F2C14E', 'lettering' => 'friendly',
        'tagline' => 'Beautiful hair', 'contact' => ['whatsapp' => true, 'area' => false, 'hours' => true]])->assertSessionHasNoErrors();
    expect(DB::table('brand_kits')->value('primary'))->toBe('#7A1F5C')
        ->and(DB::table('platform_events')->where('name', 'brand.kit.saved')->count())->toBe(1);

    $this->post('/brand/kit/logo', ['logo' => UploadedFile::fake()->image('logo.png', 900, 900)])->assertSessionHasErrors('own'); // must confirm it's theirs
    $this->post('/brand/kit/logo', ['logo' => UploadedFile::fake()->image('logo.png', 900, 900), 'own' => true])->assertSessionHasNoErrors();
    $path = (string) DB::table('brand_kits')->value('logo_path');
    expect($path)->toEndWith('.webp')->and(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBe(600);

    Staff::signIn($this, User::factory()->create(['home_hub_id' => $this->hub->id]));
    $this->get('/brand')->assertNotFound(); // no business
    HubModule::query()->where('hub_id', $this->hub->id)->where('module', 'Brand')->update(['enabled' => false]);
    Staff::signIn($this, $this->owner);
    $this->get('/brand')->assertNotFound();
});

it('saves, duplicates and downloads designs, pre-filling the price list from listed products only', function (): void {
    $shop = Shop::query()->create(['business_id' => $this->business->id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3, 'opened_by' => $this->owner->id]);
    $this->seed(MarketCategorySeeder::class);
    $cat = (int) DB::table('market_categories')->value('id');
    Product::query()->create(['shop_id' => $shop->id, 'category_id' => $cat, 'title' => 'Box braids', 'price_cents' => 35000, 'unit' => 'session', 'stock_type' => 'service', 'status' => 'published', 'views' => 0]);
    Product::query()->create(['shop_id' => $shop->id, 'category_id' => $cat, 'title' => 'Secret draft', 'price_cents' => 100, 'unit' => 'each', 'stock_type' => 'service', 'status' => 'draft', 'views' => 0]);

    Staff::signIn($this, $this->owner);
    $this->get('/brand/make/price_list')->assertInertia(fn ($p) => $p->component('Brand/Editor')->where('prefill.items', 'Box braids | R350.00'));
    $this->post('/brand/make/flyer', ['title' => 'Grand opening', 'fields' => ['headline' => 'Now open!', 'text' => 'Come and see us']])->assertRedirect();
    $id = (int) DB::table('brand_designs')->value('id');
    $this->post("/brand/designs/{$id}/duplicate")->assertRedirect();
    expect(DB::table('brand_designs')->count())->toBe(2);

    $this->post('/brand/make/flyer/exported')->assertNoContent();
    $this->post('/brand/make/flyer/pdf', ['fields' => ['headline' => '<script>x</script>', 'text' => 'Hi']])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(DB::table('brand_exports')->pluck('format')->sort()->values()->all())->toBe(['pdf', 'png'])
        ->and(DB::table('platform_events')->where('name', 'brand.design.exported')->count())->toBe(2);
});

it('builds a catalogue of up to 30 listed products with a ready message, and reports a brand kit to partners', function (): void {
    $shop = Shop::query()->create(['business_id' => $this->business->id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3,
        'opened_by' => $this->owner->id, 'whatsapp' => '+27725550303', 'whatsapp_public' => false]);
    $this->seed(MarketCategorySeeder::class);
    $cat = (int) DB::table('market_categories')->value('id');
    $ids = [];
    foreach (range(1, 35) as $n) {
        $ids[] = Product::query()->create(['shop_id' => $shop->id, 'category_id' => $cat, 'title' => "Item {$n}", 'price_cents' => 1000 * $n, 'unit' => 'each', 'stock_type' => 'service', 'status' => 'published', 'views' => 0])->id;
    }
    $built = app(Catalogue::class)->build($this->business->id, [...$ids, 'not-mine'], 'Nomsa Hair');
    expect($built['items'])->toHaveCount(30)->and($built['message'])->toContain('Item 1 - R10.00')->toContain('/market/shops/nomsa')
        ->not->toContain('+27725550303'); // the WhatsApp number isn't public

    expect(Signals::for($this->business->id)['brand.has_kit'])->toBeFalse();
    app(Kits::class)->save($this->business->id, $this->owner, ['primary' => '#1F4E79', 'secondary' => '#F5B700']);
    expect(Signals::for($this->business->id)['brand.has_kit'])->toBeTrue();
});

it('suggests taglines through the AI writing help', function (): void {
    expect(app(Kits::class)->taglineIdeas($this->business->id, $this->owner))->not->toBeEmpty()
        ->and(DB::table('ai_requests')->where('feature', 'brand.tagline')->exists())->toBeTrue();
});
