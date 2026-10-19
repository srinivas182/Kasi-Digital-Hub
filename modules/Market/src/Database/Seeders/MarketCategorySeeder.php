<?php

declare(strict_types=1);

namespace Modules\Market\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** KasiMarket categories (S24). Names are translations: market.category.<slug>. */
final class MarketCategorySeeder extends Seeder
{
    public const SLUGS = ['food', 'produce', 'beauty', 'clothing', 'crafts', 'home', 'phones', 'services', 'second_hand', 'other'];

    public function run(): void
    {
        foreach (self::SLUGS as $i => $slug) {
            DB::table('market_categories')->insertOrIgnore(['slug' => $slug, 'position' => $i]);
        }
    }
}
