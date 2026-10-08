<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Support\Demo\SouthAfricanFaker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Structure\Models\Hub;

/**
 * ~500 fictitious citizens spread across the demo hubs, so dashboards and staff views
 * have realistic volumes. Bulk-inserted for speed (one PIN hash shared by all: 24680).
 * Numbers use the reserved demo range +2784000xxxx.
 */
final class DemoCitizensSeeder extends Seeder
{
    public const COUNT = 500;

    public function run(): void
    {
        if (DB::table('users')->where('phone', 'like', '+2784000%')->exists()) {
            return; // already seeded
        }

        $faker = new SouthAfricanFaker(seed: 2026);
        $hubs = Hub::query()->with(['municipality', 'place'])->orderBy('code')->get()->all();
        $pin = Hash::make(DemoUsersSeeder::DEMO_PIN);
        $now = now();
        $users = $roles = $consents = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $hub = $hubs[$i % count($hubs)];
            $id = (string) Str::ulid();
            $users[] = [
                'id' => $id,
                'phone' => sprintf('+2784000%04d', $i),
                'phone_verified_at' => $now,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->surname(),
                'date_of_birth' => $now->copy()->subYears(18 + ($i % 17))->subDays($i % 300)->toDateString(),
                'preferred_locale' => 'en',
                'pin' => $pin,
                'status' => 'active',
                'age_band' => 'adult',
                'home_hub_id' => $hub->id,
                'municipality_id' => $hub->municipality_id,
                'province_id' => $hub->municipality->province_id,
                'place_name' => $hub->place?->name,
                'whatsapp_opt_in' => $i % 3 !== 0,
                'created_at' => $now->copy()->subDays($i % 200),
                'updated_at' => $now,
            ];

            $citizenRoles = ['job_seeker'];
            if ($i % 3 === 0) {
                $citizenRoles[] = 'learner';
            }
            if ($i % 7 === 0) {
                $citizenRoles[] = 'entrepreneur';
            }

            foreach ($citizenRoles as $role) {
                $roles[] = ['id' => (string) Str::ulid(), 'user_id' => $id, 'role' => $role, 'scope_type' => 'self', 'scope_id' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            $consents[] = ['id' => (string) Str::ulid(), 'user_id' => $id, 'purpose' => 'platform', 'granted' => true, 'document_versions' => json_encode(['terms' => 1, 'privacy' => 1]), 'channel' => $i % 4 === 0 ? 'assisted' : 'self', 'locale' => 'en', 'created_at' => $now];
            $consents[] = ['id' => (string) Str::ulid(), 'user_id' => $id, 'purpose' => 'job_matching', 'granted' => true, 'document_versions' => null, 'channel' => 'self', 'locale' => 'en', 'created_at' => $now];
        }

        DB::transaction(function () use ($users, $roles, $consents): void {
            foreach (array_chunk($users, 250) as $chunk) {
                DB::table('users')->insert($chunk);
            }
            foreach (array_chunk($roles, 250) as $chunk) {
                DB::table('role_assignments')->insert($chunk);
            }
            foreach (array_chunk($consents, 250) as $chunk) {
                DB::table('consents')->insert($chunk);
            }
        });
    }
}
