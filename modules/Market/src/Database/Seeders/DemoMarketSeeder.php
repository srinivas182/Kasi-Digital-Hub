<?php

declare(strict_types=1);

namespace Modules\Market\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;

/** Demo: Nomsa Hair Studio's KasiMarket shop with a few live services and products (fictional). */
final class DemoMarketSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MarketCategorySeeder::class);
        $nomsa = User::query()->where('phone', '+27720000003')->first();
        if (! $nomsa instanceof User || Shop::query()->exists()) {
            return;
        }
        $business = app(BusinessFacts::class)->businessesOf($nomsa)[0] ?? null;
        if ($business === null) {
            return;
        }
        $shop = Shop::query()->create(['business_id' => $business['id'], 'slug' => 'nomsa-hair-studio', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false,
            'description' => 'Braids, cornrows and treatments in Tsutsumani. Book on WhatsApp.', 'hub_id' => $nomsa->home_hub_id, 'area' => 'Tsutsumani, near the hub',
            'whatsapp' => '+27720000003', 'whatsapp_public' => true, 'hours' => ['tue' => '08:00-17:00', 'wed' => '08:00-17:00', 'thu' => '08:00-17:00', 'fri' => '08:00-18:00', 'sat' => '07:00-15:00'],
            'approved_products' => 3, 'opened_by' => $nomsa->id]);
        app(RoleAssignments::class)->assign($nomsa, 'trader', Scope::self(), null);
        $beauty = (int) DB::table('market_categories')->where('slug', 'beauty')->value('id');
        foreach ([
            ['Box braids (shoulder length)', 35000, 'session', 'service', null], ['Cornrows', 15000, 'session', 'service', null],
            ['Hair treatment and wash', 12000, 'session', 'service', null], ['Home-made hair oil (100 ml)', 4500, 'each', 'quantity', 12],
        ] as [$title, $price, $unit, $stockType, $stock]) {
            Product::query()->create(['shop_id' => $shop->id, 'category_id' => $beauty, 'title' => $title, 'price_cents' => $price, 'unit' => $unit, 'stock_type' => $stockType,
                'stock' => $stock, 'status' => 'published', 'views' => random_int(5, 60), 'published_at' => now()->subDays(random_int(1, 20))]);
        }
    }
}
