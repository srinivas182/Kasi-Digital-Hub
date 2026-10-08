<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Identity\Models\GuardianConsent;
use Modules\Core\Identity\Models\StaffTwoFactor;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use PragmaRX\Google2FA\Google2FA;

/**
 * Demo accounts - one per main role, all fictitious. All demo accounts use PIN 24680
 * (12345 is rejected by the PIN rules as too simple). Roles are attached in Sprint 3.
 * Staff accounts already have the authenticator step set up (so demos go straight to the
 * code); in demo mode the current authenticator code is shown on screen.
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
        ['phone' => '+27720000022', 'first' => 'Vusi', 'last' => 'Ngobeni', 'dob' => '1987-02-11', 'staff' => true, 'story' => 'City coordinator - Greater Giyani'],
        ['phone' => '+27720000023', 'first' => 'Lerato', 'last' => 'Mokoena', 'dob' => '1983-10-30', 'staff' => true, 'story' => 'Provincial coordinator - Limpopo'],
        ['phone' => '+27720000024', 'first' => 'Bongani', 'last' => 'Nkosi', 'dob' => '1979-05-08', 'staff' => true, 'story' => 'Hub owner (operator) - Tsutsumani'],
        ['phone' => '+27720000030', 'first' => 'Naledi', 'last' => 'Khumalo', 'dob' => '1984-12-05', 'staff' => true, 'story' => 'Funder programme manager'],
        ['phone' => '+27720000040', 'first' => 'Lucky', 'last' => 'Siwela', 'dob' => '1980-06-18', 'staff' => true, 'story' => 'National super admin (Ku Tirhisana)'],
        ['phone' => '+27720000041', 'first' => 'Zanele', 'last' => 'Dlamini', 'dob' => '1991-03-27', 'staff' => true, 'story' => 'Finance admin (Ku Tirhisana)'],
        ['phone' => '+27720000042', 'first' => 'Ayanda', 'last' => 'Zwane', 'dob' => '1988-11-02', 'staff' => true, 'story' => 'Operations admin (Ku Tirhisana)'],
        ['phone' => '+27720000043', 'first' => 'Bheki', 'last' => 'Mthethwa', 'dob' => '1995-07-19', 'staff' => true, 'story' => 'Support agent (Ku Tirhisana)'],
        ['phone' => '+27720000050', 'first' => 'Karabo', 'last' => 'Molefe', 'dob' => '1989-09-14', 'staff' => true, 'story' => 'Partner admin - Ubuntu Community Bank (demo bank)'],
    ];

    public function run(ConsentService $consents, Google2FA $google2fa): void
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

            if (($person['staff'] ?? false) && ! StaffTwoFactor::query()->where('user_id', $user->id)->exists()) {
                StaffTwoFactor::query()->create([
                    'user_id' => $user->id,
                    'secret' => $google2fa->generateSecretKey(32),
                    'recovery_codes' => [],
                    'confirmed_at' => now(),
                ]);
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
