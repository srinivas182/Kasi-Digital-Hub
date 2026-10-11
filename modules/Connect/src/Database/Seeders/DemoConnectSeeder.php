<?php

declare(strict_types=1);

namespace Modules\Connect\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Demo: Palesa (business mentor, cleared for 16-17s, Tsutsumani hub) mentoring Nomsa; Lucky is the demo
 * safeguarding officer and Ayanda the mentor coordinator.
 */
final class DemoConnectSeeder extends Seeder
{
    public function run(RoleAssignments $roles): void
    {
        $palesa = User::query()->where('phone', '+27720000011')->first();
        $nomsa = User::query()->where('phone', '+27720000003')->first();
        if ($palesa === null || $nomsa === null || Mentor::query()->exists()) {
            return;
        }
        foreach (['+27720000040' => 'safeguarding_officer', '+27720000042' => 'mentor_coordinator'] as $phone => $role) {
            if (($u = User::query()->where('phone', $phone)->first()) !== null) {
                $roles->assign($u, $role, Scope::national());
            }
        }
        $hub = Hub::query()->where('code', 'LP-GIY-TSU')->value('id');
        Mentor::query()->create(['user_id' => $palesa->id, 'headline' => 'Business owner - catering, pricing and growing a small business (demo)',
            'bio' => 'I ran a catering business for 12 years. I help young entrepreneurs with pricing, customers and keeping records.',
            'expertise' => ['catering', 'pricing', 'bookkeeping', 'small business', 'marketing'], 'goals' => ['business', 'career', 'confidence'],
            'languages' => ['English', 'Xitsonga', 'Sepedi'], 'formats' => ['phone', 'video', 'hub'], 'hub_id' => $hub, 'municipality_id' => $palesa->municipality_id,
            'availability' => 'Weekday evenings, Saturday mornings', 'capacity' => 3, 'status' => 'approved', 'minors_ok' => true, 'approved_at' => now(), 'minutes_mentored' => 180]);
        foreach (['id_verified', 'reference_1', 'reference_2', 'code_of_conduct', 'safeguarding_course', 'police_clearance'] as $check) {
            DB::table('connect_mentor_checks')->insert(['mentor_id' => $palesa->id, 'check' => $check, 'note' => 'Demo', 'checked_at' => now()]);
        }
        $m = Mentorship::query()->create(['mentor_id' => $palesa->id, 'mentee_id' => $nomsa->id, 'goal' => 'business', 'format' => 'phone', 'status' => 'active',
            'goals' => ['Set prices that cover all my costs', 'Keep simple records every week', 'Apply for one support offer'], 'per_month' => 2, 'months' => 3,
            'mentor_agreed_at' => now()->subWeeks(3), 'mentee_agreed_at' => now()->subWeeks(3), 'starts_on' => now()->subWeeks(3)->toDateString(), 'ends_on' => now()->addWeeks(9)->toDateString()]);
        DB::table('connect_sessions')->insert([
            ['mentorship_id' => $m->id, 'starts_at' => now()->subWeeks(2), 'minutes' => 60, 'format' => 'phone', 'status' => 'held', 'helpful' => true,
                'shared_notes' => 'Worked out the real cost of a braiding session including products and transport.', 'actions' => json_encode(['Write down every cost for one week']), 'link' => null, 'event_id' => null, 'private_notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['mentorship_id' => $m->id, 'starts_at' => now()->addDays(4)->setTime(18, 0), 'minutes' => 60, 'format' => 'phone', 'status' => 'scheduled', 'helpful' => null,
                'shared_notes' => null, 'actions' => null, 'link' => null, 'event_id' => null, 'private_notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
