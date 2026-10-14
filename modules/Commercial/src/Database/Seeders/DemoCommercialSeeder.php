<?php

declare(strict_types=1);

namespace Modules\Commercial\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Commercial\Services\HubFinance;
use Modules\Core\Structure\Models\Hub;

/** Demo: Tsutsumani hub is ready to sell (fictional details); other hubs are not yet. */
final class DemoCommercialSeeder extends Seeder
{
    public function run(): void
    {
        $hub = Hub::query()->where('code', 'LP-GIY-TSU')->first();
        if ($hub === null) {
            return;
        }
        HubFinance::upsert($hub->id, ['company_registration' => '2024/000000/07 (demo)', 'vat_registered' => false, 'bank_verified' => true,
            'bank_account_label' => 'Demo Bank ...0000', 'franchise_version' => config('kasi.commercial.franchise_version'), 'franchise_accepted_at' => now()]);
    }
}
