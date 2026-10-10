<?php

declare(strict_types=1);

namespace Modules\Start\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Identity\Models\User;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;
use Modules\Start\Services\Businesses;
use Modules\Start\Services\Plans;

/** Demo: Nomsa's hair studio (sole proprietor, part-way through the journey) with a started plan. */
final class DemoStartSeeder extends Seeder
{
    public function run(Businesses $businesses, Plans $plans): void
    {
        $nomsa = User::query()->where('phone', '+27720000003')->first();
        if ($nomsa === null || Business::query()->exists()) {
            return;
        }

        $business = $businesses->create($nomsa, ['name' => 'Nomsa Hair Studio (demo)', 'sells' => 'Braids, wash-and-set and hair products', 'sector' => 'beauty',
            'stage' => 'informal', 'place_name' => 'Soweto', 'people' => 2, 'turnover_band' => '5k_20k', 'customers' => 'Women and girls in Zone 6, mostly on weekends']);
        $businesses->chooseForm($business, 'sole', $nomsa);
        foreach (['bank_account'] as $key) {
            $step = Step::query()->where('key', $key)->first();
            if ($step !== null) {
                $businesses->markDone($business, $step, $nomsa);
            }
        }
        $plans->save($business, [
            'problem' => 'Women in our area travel to town for braids and wait long. We are close by and open on weekends.',
            'offer' => 'Braids, wash-and-set, and hair products like oils and extensions.',
        ], ['cost' => 120, 'markup' => 150, 'fixed' => 1500], $nomsa);
    }
}
