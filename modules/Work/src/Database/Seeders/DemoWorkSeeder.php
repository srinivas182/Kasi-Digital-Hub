<?php

declare(strict_types=1);

namespace Modules\Work\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Work\Matching\MatchIndex;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\OfoOccupation;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\CvComposer;
use Modules\Work\Services\Hiring;
use Modules\Work\Services\SeekerProfile;

/**
 * Demo job profiles: Thandi has a complete profile and a CV; Lwazi has started his.
 * Informal work is shown as real experience. All details are fictitious.
 */
final class DemoWorkSeeder extends Seeder
{
    public function run(SeekerProfile $profiles, CvComposer $cvs): void
    {
        $this->employers();

        $thandi = User::query()->where('phone', '+27720000001')->first();
        $lwazi = User::query()->where('phone', '+27720000002')->first();
        if ($thandi === null || DB::table('work_profiles')->where('user_id', $thandi->id)->exists()) {
            return;
        }

        $profiles->save($thandi, [
            'headline' => 'Friendly cashier and stock assistant',
            'summary' => 'Reliable and friendly, with experience serving customers and handling cash in a busy family spaza shop. Quick to learn and good with numbers.',
            'drivers_licence' => 'none', 'own_transport' => false,
            'work_types' => ['full_time', 'part_time', 'learnership'], 'sectors' => ['retail', 'hospitality'],
            'max_travel_km' => 30, 'available_from' => 'now',
        ]);

        WorkExperience::query()->create([
            'user_id' => $thandi->id, 'kind' => 'family_business', 'title' => 'Shop assistant', 'organisation' => 'Mabasa family spaza shop',
            'place' => 'Tsutsumani', 'started' => now()->subYears(3)->format('Y-m'), 'position' => 0,
            'description' => 'I help at my aunt\'s tuck shop after school and weekends. I serve customers, count the money and pack the stock.',
            'bullets' => ['Served customers and handled cash at a busy family spaza shop', 'Counted and packed stock and kept shelves tidy'],
        ]);
        WorkExperience::query()->create([
            'user_id' => $thandi->id, 'kind' => 'piece_work', 'title' => 'Event helper', 'place' => 'Giyani', 'duration' => 'Weekends, about 1 year', 'position' => 1,
            'description' => 'Setting up chairs and tents for funerals and weddings, cleaning after events.',
        ]);

        $matric = Document::query()->where('user_id', $thandi->id)->where('type', 'matric_certificate')->value('id');
        WorkEducation::query()->create([
            'user_id' => $thandi->id, 'kind' => 'matric', 'name' => 'National Senior Certificate (Matric)', 'institution' => 'Tsutsumani Secondary School (demo)',
            'year' => now()->subYears(2)->year, 'details' => 'Mathematical Literacy, Business Studies', 'document_id' => $matric,
        ]);

        DB::table('work_skills')->insert(array_map(static fn (string $s): array => ['user_id' => $thandi->id, 'name' => $s], ['Customer service', 'Cash handling', 'Stock counting', 'Microsoft Word', 'Teamwork']));
        DB::table('work_languages')->insert([
            ['user_id' => $thandi->id, 'language' => 'Xitsonga', 'level' => 'fluent'],
            ['user_id' => $thandi->id, 'language' => 'English', 'level' => 'good'],
            ['user_id' => $thandi->id, 'language' => 'Sepedi', 'level' => 'basic'],
        ]);
        $profiles->refresh($thandi);
        $cvs->create($thandi, 'classic');

        if ($lwazi !== null) {
            $profiles->save($lwazi, ['headline' => 'Hard-working general worker', 'summary' => 'I am looking for work in construction or farming.', 'drivers_licence' => 'B', 'own_transport' => false]);
            WorkExperience::query()->create([
                'user_id' => $lwazi->id, 'kind' => 'piece_work', 'title' => 'General worker', 'place' => 'Giyani', 'duration' => 'About 2 years',
                'description' => 'piece jobs mixing cement, carrying bricks, digging trenches for builders in my area', 'position' => 0,
            ]);
            $profiles->refresh($lwazi);
        }

        // Matches for the demo profiles (S11).
        app(MatchIndex::class)->refreshAll();

        // A demo application in the hiring pipeline (S12): Thandi applied for the cashier job and is shortlisted.
        $cashier = JobListing::query()->where('title', 'Cashier')->first();
        $sipho = User::query()->where('phone', '+27720000010')->first();
        if ($cashier !== null && $sipho !== null) {
            $hiring = app(Hiring::class);
            $application = $hiring->apply($cashier, $thandi, null, ['Yes'], 'I live in Tsutsumani and can start immediately.');
            $hiring->move($application->load(['listing.organisation', 'user']), 'shortlisted', $sipho);
        }
    }

    /** Demo employers and live listings (S10). All fictitious. */
    private function employers(): void
    {
        if (JobListing::query()->exists()) {
            return;
        }

        $mopani = Organisation::query()->where('name', 'Mopani Fresh Market')->first();
        $sipho = User::query()->where('phone', '+27720000010')->first();
        if ($mopani === null || $sipho === null) {
            return;
        }

        $mopani->forceFill([
            'trading_name' => 'Mopani Fresh Market (demo)', 'sector' => 'retail', 'size_band' => '11-50', 'verification_status' => 'verified', 'verified_at' => now(),
            'description' => 'A family-owned fresh produce and grocery store serving Giyani and surrounding villages. Demo business - not real.',
            'verification_checklist' => ['cipc_found' => true, 'cipc_active' => true, 'person_linked' => true, 'phone_answered' => true],
        ])->save();

        $city = static fn (string $code): ?\Modules\Core\Structure\Models\Municipality => Municipality::query()->where('code', $code)->first();
        $occupation = static fn (string $title): ?int => OfoOccupation::query()->where('title', $title)->value('id');
        $listings = [
            ['Cashier', 'Cashier', 'LIM331', 'Giyani', 'part_time', 2, 32, 'hour', 'none', 'Weekends and public holidays, 07:00-16:00',
                'We need two friendly cashiers for weekends at our Giyani store. You will serve customers, handle cash and card payments and keep the till area tidy. No experience needed - we train you. Matric preferred but not required.',
                [['Customer service', true], ['Cash handling', false]], ['Can you work weekends and public holidays?']],
            ['Shelf packer', 'Shelf packer / merchandiser', 'LIM331', 'Giyani', 'full_time', 3, 30, 'hour', 'none', 'Monday to Friday, 06:00-15:00',
                'Pack shelves, receive deliveries and keep the store neat. Early starts. Good for someone hard-working and reliable. Training given on the job.',
                [['Teamwork', true], ['Stock counting', false]], ['Can you start work at 06:00?']],
            ['Fruit and vegetable assistant', 'Sales assistant', 'LIM331', 'Tsutsumani', 'full_time', 1, 6200, 'month', 'some', 'Monday to Saturday',
                'Help customers choose fresh produce, weigh and price items and keep the fruit and vegetable section fresh and tidy. Some shop experience (including a family spaza) is welcome.',
                [['Customer service', true]], []],
            ['Delivery driver', 'Delivery driver', 'LIM331', 'Giyani', 'full_time', 1, 7500, 'month', '1_year', 'Monday to Friday, 07:00-16:00',
                'Deliver grocery orders to customers in Giyani and nearby villages with our bakkie. You need a valid code B licence and must know the area well.',
                [['Driving', true]], ['Do you have a valid code B driver\'s licence?']],
            ['Retail learnership (12 months)', 'Sales assistant', 'LIM331', 'Giyani', 'learnership', 4, 3500, 'month', 'none', 'Monday to Friday',
                'A 12-month retail learnership with a monthly stipend: classroom training with an accredited provider and work experience in our store. For young people with matric who want a career in retail.',
                [['Customer service', false]], ['Do you have your matric certificate?']],
        ];

        foreach ($listings as [$title, $occ, $code, $place, $type, $positions, $pay, $period, $exp, $hours, $description, $skills, $questions]) {
            $m = $city($code);
            $listing = JobListing::query()->create([
                'organisation_id' => $mopani->id, 'created_by' => $sipho->id, 'title' => $title, 'occupation_id' => $occupation($occ),
                'type' => $type, 'positions' => $positions, 'municipality_id' => $m?->id, 'place_name' => $place,
                'latitude' => $m?->latitude, 'longitude' => $m?->longitude,
                'pay_min_cents' => $pay * 100, 'pay_period' => $period, 'hours' => $hours, 'education' => $type === 'learnership' ? 'matric' : 'none',
                'experience' => $exp, 'languages' => ['English', 'Xitsonga'], 'description' => $description,
                'closes_on' => now()->addDays(21)->toDateString(), 'published_at' => now()->subDays(2),
                // Within the free limit of 3 active adverts: two older adverts are already filled/closed.
                'status' => match ($title) {
                    'Delivery driver' => 'filled', 'Fruit and vegetable assistant' => 'closed', default => 'live'
                },
            ]);
            foreach ($skills as [$name, $must]) {
                $listing->skills()->create(['name' => $name, 'must' => $must]);
            }
            foreach ($questions as $i => $q) {
                $listing->questions()->create(['question' => $q, 'kind' => 'yes_no', 'position' => $i]);
            }
        }

        // A community employer waiting for a hub visit.
        $rose = User::query()->where('phone', '+2784000'.'0007')->first();
        if ($rose !== null) {
            $org = Organisation::query()->create([
                'type' => 'employer', 'name' => 'Mama Rose Kitchen (demo)', 'community' => true, 'sector' => 'hospitality', 'size_band' => '1',
                'municipality_id' => $city('LIM331')?->id, 'verification_status' => 'pending', 'contact_phone' => $rose->phone,
                'description' => 'Home kitchen selling plates and vetkoek at the Giyani taxi rank. Demo business - not real.',
            ]);
            $org->members()->attach($rose->id, ['title' => 'Owner']);
            app(RoleAssignments::class)->assign($rose, 'employer_admin', Scope::organisation($org));
        }
    }
}
