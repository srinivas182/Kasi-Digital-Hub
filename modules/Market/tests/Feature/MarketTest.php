<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Access\Scope;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Market\Database\Seeders\MarketCategorySeeder;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Shops;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('public');
    $this->seed(MarketCategorySeeder::class);
    $this->seed(StepSeeder::class);
    $this->hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $this->hub->id, 'module' => 'Market'], ['enabled' => true]);
    $this->trader = User::factory()->create(['first_name' => 'Nomsa', 'last_name' => 'Zwelibanzi-Mkhatshwa', 'home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(24)]);
    $this->business = app(Businesses::class)->create($this->trader, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->category = (int) DB::table('market_categories')->where('slug', 'beauty')->value('id');
});

function openShop(TestCase $t, array $o = []): Shop
{
    Staff::signIn($t, $t->trader);
    $t->post('/market/shop', ['business_id' => $t->business->id, 'name' => 'Nomsa Hair Studio', 'description' => 'Braids and cuts', 'whatsapp' => '072 555 0303', 'whatsapp_public' => false, ...$o])
        ->assertSessionHasNoErrors();

    return Shop::query()->sole();
}

function addProduct(TestCase $t, string $title = 'Box braids', bool $publish = true): Product
{
    $t->post('/market/shop/products', ['title' => $title, 'category_id' => $t->category, 'price_cents' => 35000, 'unit' => 'session', 'stock_type' => 'service',
        'photo' => UploadedFile::fake()->image('braids.jpg', 1600, 1200), 'photo_alt' => 'Box braids, shoulder length', 'publish' => $publish]);

    return Product::query()->where('title', $title)->firstOrFail();
}

it('opens a shop for a KasiStart business, makes the person a trader, and keeps WhatsApp private unless agreed', function (): void {
    $shop = openShop($this);
    expect($shop->slug)->toBe('nomsa-hair-studio')->and($shop->whatsapp)->toBe('+27725550303')
        ->and(DB::table('role_assignments')->where('user_id', $this->trader->id)->where('role', 'trader')->exists())->toBeTrue();

    $this->get('/market/shops/nomsa-hair-studio')->assertInertia(fn ($page) => $page->where('shop.whatsapp', null));
    $this->put('/market/shop', ['name' => 'Nomsa Hair Studio', 'whatsapp' => '0725550303', 'whatsapp_public' => true])->assertSessionHasNoErrors();
    $this->get('/market/shops/nomsa-hair-studio')->assertInertia(fn ($page) => $page->where('shop.whatsapp', '+27725550303'));
});

it('only lets adult owners at hubs with KasiMarket open a shop', function (): void {
    $young = User::factory()->create(['home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(17)]);
    $other = User::factory()->create(['home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(30)]);
    $svc = app(Shops::class);
    expect($svc->blocker($other, $this->business->id))->toBe('market.shop.not_member');

    $youngBusiness = app(Businesses::class)->create($young, ['name' => 'Teen Crafts', 'sells' => 'Beads', 'sector' => 'creative', 'stage' => 'idea', 'people' => 1]);
    expect($svc->blocker($young, $youngBusiness->id))->toBe('market.shop.age');

    HubModule::query()->where('hub_id', $this->hub->id)->where('module', 'Market')->update(['enabled' => false]);
    expect($svc->blocker($this->trader, $this->business->id))->toBe('market.shop.hub');
});

it('compresses photos into small WebP versions, needs alt text, and limits photos to 4', function (): void {
    openShop($this);
    $product = addProduct($this, publish: false);
    $photo = $product->photos()->sole();
    expect(Storage::disk('public')->size($photo->small_path))->toBeLessThan(40_000)
        ->and(getimagesizefromstring(Storage::disk('public')->get($photo->small_path))[0])->toBe(320);

    $this->post("/market/shop/products/{$product->id}", ['title' => 'Box braids', 'category_id' => $this->category, 'price_cents' => 35000, 'unit' => 'session',
        'stock_type' => 'service', 'photo' => UploadedFile::fake()->image('x.jpg')])->assertSessionHasErrors('photo_alt');
});

it('reviews the first 3 products, then publishes automatically; refuses prohibited items', function (): void {
    $shop = openShop($this);
    $first = addProduct($this, 'Box braids');
    expect($first->status)->toBe('review')->and(ModerationFlag::query()->where('subject_type', 'market_product')->count())->toBe(1);
    $this->get('/market/products/'.$first->id)->assertNotFound(); // not public while in review

    // Approving in the review inbox publishes it.
    Staff::signIn($this, Structure::personWith('content_reviewer', Scope::national()));
    $this->post('/admin/inbox/approve', ['ids' => [ModerationFlag::query()->value('id')], 'reason' => 'Looks fine'])->assertSessionHasNoErrors();
    expect($first->refresh()->status)->toBe('published')->and($shop->refresh()->approved_products)->toBe(1);

    $shop->forceFill(['approved_products' => 3])->save();
    Staff::signIn($this, $this->trader);
    expect(addProduct($this, 'Cornrows')->status)->toBe('published')
        ->and(addProduct($this, 'Brandy and beer pack')->status)->toBe('refused');
    $this->get('/market/shops/nomsa-hair-studio')->assertInertia(fn ($page) => $page->has('products', 2));
});

it('marks quantity products sold out at zero stock and keeps public pages free of personal details', function (): void {
    $shop = openShop($this);
    $shop->forceFill(['approved_products' => 3])->save();
    $this->post('/market/shop/products', ['title' => 'Hair oil', 'category_id' => $this->category, 'price_cents' => 4500, 'unit' => 'each', 'stock_type' => 'quantity', 'stock' => 0, 'publish' => true]);
    $oil = Product::query()->where('title', 'Hair oil')->sole();
    expect($oil->soldOut())->toBeTrue();

    Staff::signIn($this, User::factory()->create()); // seen by someone else, not the trader
    $page = json_encode($this->get('/market/products/'.$oil->id)->viewData('page'));
    expect($page)->not->toContain($this->trader->phone)->not->toContain((string) $this->trader->last_name)->not->toContain('+27725550303');
    $this->get('/market/search?q=oil')->assertInertia(fn ($p) => $p->where('results.total', 1));
    $this->get('/market/search?in_stock=1&q=oil')->assertInertia(fn ($p) => $p->where('results.total', 0));
});

it('lets signed-in people report a product into the review inbox, and others never manage a shop they do not own', function (): void {
    $shop = openShop($this);
    $shop->forceFill(['approved_products' => 3])->save();
    $product = addProduct($this, 'Cornrows');
    $buyer = Staff::signIn($this, User::factory()->create());
    $this->post("/market/products/{$product->id}/report", ['reason' => 'scam', 'note' => 'Asked me to pay first'])->assertSessionHasNoErrors();
    expect(ModerationFlag::query()->where('subject_id', $product->id)->exists())->toBeTrue();

    $this->get("/market/shop/products/{$product->id}")->assertNotFound();
    $this->post("/market/shop/products/{$product->id}/publish")->assertNotFound();
});
