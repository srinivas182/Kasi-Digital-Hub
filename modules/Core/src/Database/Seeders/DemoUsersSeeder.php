<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Identity\Models\GuardianConsent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;

/**
 * Demo accounts - one per main role, all fictitious. All demo accounts use PIN 24680
 * (12345 is rejected by the PIN rules as too simple). Roles are attached in Sprint 3.
 * Staff accounts use the authenticator step; in demo mode the current code is shown on screen.
 */
final class DemoUsersSeeder extends Seeder
{
    public const DEMO_PIN = '24680';

    /** @var list<array{phone: string, first: string, last: string, dob: string, staff?: bool, minor?: bool, story: string}> */
    public const PEOPLE = [
        ['phone' => '+27720000001', 'first' => 'Thandi', 'last' => 'Mabasa', 'dob' => '2004-05-14', 'story' => 'Job seeker, learner and later trader (Tsutsumani)'],
        ['phone' => '+27720000002', 'first' => 'Lwazi', 'last' => 'Chauke', 'dob' => '2002-09-03', 'story' => 'Learner on a sponsored retail learnership'],
        ['phone' => '+27720000003', 'first' => 'Nomsa', 'last' => 'Mthembu', 'dob' => '1996-02-21', 'story' => 'Entrepreneur - catering and baking business'],
        ['phone' => '+27720000004', 'first' => 'Kurhula', 'last' => 'Mabunda', 'dob' => '2009-11-30', 'minor' => true, 'story' => '17-year-old learner (guardian consent given)'],
        ['phone' => '+27720000010', 'first' => 'Sipho', 'last' => 'Nkuna', 'dob' => '1988-07-09', 'staff' => true, 'story' => 'Employer - Mopani Fresh Market'],
        ['phone' => '+27720000011', 'first' => 'Palesa', 'last' => 'Molefe', 'dob' => '1985-01-17', 'staff' => true, 'story' => 'Business mentor'],
        ['phone' => '+27720000012', 'first' => 'Herman', 'last' => 'Moolman', 'dob' => '1960-04-02', 'staff' => true, 'story' => 'Training provider (HBM EduTech)'],
        ['phone' => '+27720000020', 'first' => 'Rhulani', 'last' => 'Baloyi', 'dob' => '1993-03-12', 'staff' => true, 'story' => 'Hub facilitator - Tsutsumani'],
        ['phone' => '+27720000021', 'first' => 'Tsakani', 'last' => 'Mathebula', 'dob' => '1990-08-25', 'staff' => true, 'story' => 'Hub manager - Tsutsumani'],
        ['phone' => '+27720000030', 'first' => 'Naledi', 'last' => 'Khumalo', 'dob' => '1984-12-05', 'staff' => true, 'story' => 'Funder programme manager'],
        ['phone' => '+27720000040', 'first' => 'Lucky', 'last' => 'Siwela', 'dob' => '1980-06-18', 'staff' => true, 'story' => 'National super admin (Ku Tirhisana)'],
    ];

    public function run(ConsentService $consents): void
    {
        $this->call(ConsentDocumentSeeder::class);

        foreach (self::PEOPLE as $person) {
            $user = User::query()->updateOrCreate(['phone' => $person['phone']], [
                'phone_verified_at' => now(),
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'date_of_birth' => $person['dob'],
                'preferred_locale' => 'en',
                'pin' => self::DEMO_PIN,
                'status' => User::STATUS_ACTIVE,
                'age_band' => ($person['minor'] ?? false) ? 'minor' : 'adult',
                'two_factor_required' => $person['staff'] ?? false,
                'whatsapp_opt_in' => true,
            ]);

            if ($consents->state($user)['platform'] === null) {
                $consents->record($user, ['platform' => true, 'job_matching' => true, 'learning_records' => true, 'whatsapp_updates' => true]);
            }

            if (($person['minor'] ?? false) && ! $user->guardianConsents()->exists()) {
                GuardianConsent::query()->create([
                    'user_id' => $user->id,
                    'guardian_name' => 'Grace Mabunda',
                    'guardian_phone' => '+27720000099',
                    'relationship' => 'parent',
                    'verified_at' => now(),
                ]);
            }
        }
    }
}
