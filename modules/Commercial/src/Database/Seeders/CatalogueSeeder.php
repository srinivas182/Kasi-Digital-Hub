<?php

declare(strict_types=1);

namespace Modules\Commercial\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Commercial\Models\Product;

/**
 * Release 1 catalogue with PLACEHOLDER prices and split percentages (S19). Ku Tirhisana confirms the real
 * numbers; changes then go through four-eyes approval in the console. Notes mark every placeholder.
 */
final class CatalogueSeeder extends Seeder
{
    /** key => [stream, name, unit, merchant, metro price (cents, VAT incl.), rural price] */
    public const PRODUCTS = [
        'package_base' => ['package', 'Hub package: Base', 'month', 'kasi', 300000, 200000],
        'package_growth' => ['package', 'Hub package: Growth', 'month', 'kasi', 500000, 350000],
        'package_enterprise' => ['package', 'Hub package: Enterprise', 'month', 'kasi', 750000, 500000],
        'package_full' => ['package', 'Hub package: Full', 'month', 'kasi', 1000000, 700000],
        'work_extra_advert' => ['work', 'KasiWork: extra live advert (30 days)', 'advert', 'hub', 25000, 15000],
        'start_assisted' => ['start', 'KasiStart: assisted registration session', 'session', 'hub', 35000, 25000],
        'onsite_print_page' => ['onsite', 'Printing (A4 page)', 'page', 'hub', 300, 200],
        'onsite_desk_day' => ['onsite', 'Desk for a day', 'day', 'hub', 10000, 6000],
        'onsite_room_hour' => ['onsite', 'Meeting room (hour)', 'hour', 'hub', 15000, 10000],
        // S26: KasiMarket orders paid online - the amount comes from the order (price band not used).
        'market_order' => ['market', 'KasiMarket order', 'order', 'trader', 1, 1],
    ];

    /** stream => [kasi %, hub %, partner %] - placeholders following "who delivers the value" */
    public const RULES = [
        'package' => [100, 0, 0],
        'work' => [60, 40, 0],   // the platform delivers the product: Kasi keeps more
        'learn' => [20, 30, 50], // partner (training provider) delivers the course
        'start' => [20, 80, 0],  // the hub delivers the service
        'onsite' => [10, 90, 0], // the hub delivers the service
        'licence' => [100, 0, 0],
        'market' => [5, 3, 92], // S26: Kasi, hub, seller ("partner" column = the trader) - PLACEHOLDER
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $key => [$stream, $name, $unit, $merchant, $metro, $rural]) {
            $product = Product::query()->firstOrCreate(['key' => $key], ['stream' => $stream, 'name' => $name, 'unit' => $unit, 'merchant' => $merchant]);
            foreach (['metro' => $metro, 'rural' => $rural] as $tier => $price) {
                if (! DB::table('com_prices')->where('product_id', $product->id)->where('tier', $tier)->exists()) {
                    DB::table('com_prices')->insert(['product_id' => $product->id, 'tier' => $tier, 'price_cents' => $price, 'floor_cents' => (int) round($price * 0.8),
                        'ceiling_cents' => (int) round($price * 1.2), 'effective_from' => now()->subDay()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        foreach (self::RULES as $stream => [$kasi, $hub, $partner]) {
            if (! DB::table('com_split_rules')->where('stream', $stream)->exists()) {
                DB::table('com_split_rules')->insert(['stream' => $stream, 'kasi_bp' => $kasi * 100, 'hub_bp' => $hub * 100, 'partner_bp' => $partner * 100,
                    'effective_from' => now()->subDay()->toDateString(), 'status' => 'active', 'note' => 'PLACEHOLDER - to be confirmed by Ku Tirhisana', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
