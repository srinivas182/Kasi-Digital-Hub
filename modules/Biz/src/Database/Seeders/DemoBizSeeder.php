<?php

declare(strict_types=1);

namespace Modules\Biz\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Biz\Services\Books;
use Modules\Biz\Services\Owed;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;

/** Demo: three months of books for Nomsa Hair Studio, and a customer who owes money (fictional). */
final class DemoBizSeeder extends Seeder
{
    public function run(): void
    {
        $nomsa = User::query()->where('phone', '+27720000003')->first();
        $business = $nomsa instanceof User ? (app(BusinessFacts::class)->businessesOf($nomsa)[0] ?? null) : null;
        if ($business === null || DB::table('biz_entries')->where('business_id', $business['id'])->exists()) {
            return;
        }
        $books = app(Books::class);
        $books->setOpening($business['id'], ['cash' => 50000, 'card' => 0, 'mobile' => 0], $nomsa);
        foreach ([2, 1, 0] as $ago) {
            $day = static fn (int $d): string => now()->subMonthsNoOverflow($ago)->startOfMonth()->addDays($d)->toDateString();
            foreach ([
                ['in', 180000 + $ago * 15000, 'sales', 'cash', 3, 'Braids and cornrows'], ['in', 45000, 'sales', 'mobile', 10, 'Treatments'],
                ['out', 60000, 'stock_materials', 'cash', 2, 'Hair extensions and oil'], ['out', 80000, 'rent', 'card', 1, 'Salon rent'],
                ['out', 12000, 'transport', 'cash', 12, 'Taxi to the wholesaler'], ['out', 5000, 'airtime_data', 'mobile', 15, 'Data bundle'],
                ['out', 20000, 'owner_took_out', 'cash', 20, null],
            ] as [$dir, $cents, $cat, $method, $d, $note]) {
                $books->record($business['id'], $nomsa, ['direction' => $dir, 'amount_cents' => $cents, 'category' => $cat, 'method' => $method,
                    'occurred_on' => min($day($d), now()->toDateString()), 'note' => $note]);
            }
        }
        $owed = app(Owed::class);
        $gogo = $owed->contact($business['id'], 'customer', 'Gogo Dlamini', '0725550101', 'Regular - pays at month end');
        $owed->add($business['id'], $gogo, 'owed_me', 15000, 'Braids on credit', now()->addDays(10)->toDateString(), $nomsa);
        $supplier = $owed->contact($business['id'], 'supplier', 'Soweto Hair Wholesale', null, null);
        $owed->add($business['id'], $supplier, 'i_owe', 30000, 'Extensions on account', now()->addDays(20)->toDateString(), $nomsa);
    }
}
