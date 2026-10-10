<?php

declare(strict_types=1);

namespace Modules\Partner\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Partner\Models\Offer;

/**
 * Demo partner: "Ubuntu Community Bank (demo)" - verified, with an accepted data-sharing agreement
 * and three offers. The bank and its offers are fictional.
 */
final class DemoPartnerSeeder extends Seeder
{
    public function run(): void
    {
        if (Offer::query()->exists()) {
            return;
        }
        // The demo bank and its partner admin come from the core demo (+27720000050).
        $partner = Organisation::query()->where('type', 'partner')->where('name', 'Ubuntu Community Bank (demo)')->first();
        $admin = User::query()->where('phone', '+27720000050')->first();
        if ($partner === null || $admin === null) {
            return;
        }
        $partner->forceFill(['verification_status' => 'verified', 'verified_at' => now(),
            'description' => 'A fictional community bank for demos: equipment grants, start-up loans and business training.'])->save();
        DB::table('partner_agreements')->insert(['organisation_id' => $partner->id, 'accepted_by' => $admin->id, 'version' => (string) config('kasi.partner.agreement_version'), 'accepted_at' => now()]);

        $base = ['organisation_id' => $partner->id, 'status' => 'open', 'opens_on' => now()->subWeek()->toDateString(), 'created_by' => $admin->id];
        Offer::query()->create([...$base, 'title' => 'Equipment grant for young entrepreneurs (demo)', 'type' => 'equipment',
            'description' => 'Up to R15 000 of equipment (for example clippers, a gas stove or a sewing machine) for youth-owned businesses that are already trading.',
            'value_min_cents' => 200000, 'value_max_cents' => 1500000, 'closes_on' => now()->addMonths(2)->toDateString(),
            'criteria' => ['stages' => ['informal', 'registered'], 'age_max' => 35, 'steps' => ['bank_account']], 'documents' => ['id_document']]);
        Offer::query()->create([...$base, 'title' => 'Start-up loan (demo)', 'type' => 'loan',
            'description' => 'Small loans for registered businesses with a business plan. Repayments are planned with you after a visit to your business.',
            'value_min_cents' => 1000000, 'value_max_cents' => 5000000, 'closes_on' => now()->addMonths(3)->toDateString(),
            'criteria' => ['stages' => ['registered'], 'steps' => ['cipc_register', 'sars_tax', 'bank_account'], 'min_readiness' => 60], 'documents' => ['cipc_certificate', 'bank_confirmation']]);
        Offer::query()->create([...$base, 'title' => 'Business basics training (demo)', 'type' => 'training',
            'description' => 'Six Saturday sessions on bookkeeping, pricing and marketing at your hub, for any entrepreneur.',
            'closes_on' => now()->addMonth()->toDateString(), 'criteria' => [], 'documents' => []]);
    }
}
