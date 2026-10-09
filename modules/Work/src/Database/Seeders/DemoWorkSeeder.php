<?php

declare(strict_types=1);

namespace Modules\Work\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\CvComposer;
use Modules\Work\Services\SeekerProfile;

/**
 * Demo job profiles: Thandi has a complete profile and a CV; Lwazi has started his.
 * Informal work is shown as real experience. All details are fictitious.
 */
final class DemoWorkSeeder extends Seeder
{
    public function run(SeekerProfile $profiles, CvComposer $cvs): void
    {
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
    }
}
