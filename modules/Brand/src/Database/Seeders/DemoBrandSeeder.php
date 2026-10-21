<?php

declare(strict_types=1);

namespace Modules\Brand\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;

/** Demo: Nomsa Hair Studio's brand kit (text logo, warm palette) and one saved flyer. */
final class DemoBrandSeeder extends Seeder
{
    public function run(): void
    {
        $nomsa = User::query()->where('phone', '+27720000003')->first();
        $business = $nomsa instanceof User ? (app(BusinessFacts::class)->businessesOf($nomsa)[0] ?? null) : null;
        if ($business === null || DB::table('brand_kits')->where('business_id', $business['id'])->exists()) {
            return;
        }
        DB::table('brand_kits')->insert(['business_id' => $business['id'], 'logo_kind' => 'text', 'logo_shape' => 'circle', 'logo_text' => 'NHS', 'primary' => '#7A1F5C',
            'secondary' => '#F2C14E', 'lettering' => 'friendly', 'tagline' => 'Beautiful hair, done with love', 'contact' => json_encode(['whatsapp' => true, 'area' => true, 'hours' => true]),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('brand_designs')->insert(['business_id' => $business['id'], 'template' => 'offer', 'title' => 'December braids special',
            'fields' => json_encode(['headline' => 'December special', 'text' => 'Box braids R300 (normally R350)', 'until' => now()->addMonth()->toDateString()]),
            'created_at' => now(), 'updated_at' => now()]);
    }
}
