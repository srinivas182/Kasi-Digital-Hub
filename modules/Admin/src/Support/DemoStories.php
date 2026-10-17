<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

/** The six guided demo journeys (S21): who to be, where to go, what to notice. */
final class DemoStories
{
    /** @return list<array{key: string, persona: string, phone: string, steps: list<array{url: string, notice: string}>}> */
    public static function all(): array
    {
        $s = static fn (string $url, string $key): array => ['url' => $url, 'notice' => __('admin.demo.notice.'.$key)];

        return [
            ['key' => 'job_seeker', 'persona' => 'Thandi Mabasa', 'phone' => '+27720000001', 'steps' => [
                $s('/home', 'home'), $s('/work/profile', 'profile'), $s('/work/cv', 'cv'), $s('/work/matches', 'matches'), $s('/work/applications', 'applications'), $s('/learn/certificates', 'certificates')]],
            ['key' => 'learner', 'persona' => 'Lwazi Chauke', 'phone' => '+27720000002', 'steps' => [
                $s('/learn/courses', 'catalogue'), $s('/learn/my', 'my_learning'), $s('/learn/offline', 'offline'), $s('/learn/join/KASI24', 'cohort'), $s('/connect/find?goal=business', 'mentor')]],
            ['key' => 'entrepreneur', 'persona' => 'Nomsa Mthembu', 'phone' => '+27720000003', 'steps' => [
                $s('/start', 'business'), $s('/support', 'support'), $s('/connect', 'mentorship'), $s('/help/register-business', 'help')]],
            ['key' => 'employer', 'persona' => 'Sipho Nkuna', 'phone' => '+27720000010', 'steps' => [
                $s('/work/employer', 'employer')]], // the extra-advert purchase starts from the employer page
            ['key' => 'hub', 'persona' => 'Tsutsumani hub owner', 'phone' => '+27720000024', 'steps' => [
                $s('/hub-ops', 'hub_home'), $s('/commercial/hub', 'pos')]],
            ['key' => 'funder', 'persona' => 'Naledi Khumalo (funder)', 'phone' => '+27720000030', 'steps' => [
                $s('/funder', 'funder')]],
        ];
    }

    /** @return list<array{name: string, phone: string, role: string}> */
    public static function personas(): array
    {
        return [
            ['name' => 'Thandi Mabasa', 'phone' => '+27720000001', 'role' => 'job_seeker'], ['name' => 'Lwazi Chauke', 'phone' => '+27720000002', 'role' => 'learner'],
            ['name' => 'Nomsa Mthembu', 'phone' => '+27720000003', 'role' => 'entrepreneur'], ['name' => 'Sipho Nkuna', 'phone' => '+27720000010', 'role' => 'employer_admin'],
            ['name' => 'Palesa Molefe', 'phone' => '+27720000011', 'role' => 'mentor'], ['name' => 'Herman Moolman', 'phone' => '+27720000012', 'role' => 'provider_admin'],
            ['name' => 'Rhulani Baloyi', 'phone' => '+27720000020', 'role' => 'hub_facilitator'], ['name' => 'Tsutsumani hub owner', 'phone' => '+27720000024', 'role' => 'hub_owner'],
            ['name' => 'Limpopo coordinator', 'phone' => '+27720000023', 'role' => 'provincial_coordinator'], ['name' => 'Naledi Khumalo', 'phone' => '+27720000030', 'role' => 'funder_manager'],
            ['name' => 'Karabo (Ubuntu Community Bank)', 'phone' => '+27720000050', 'role' => 'partner_admin'], ['name' => 'Lucky Siwela', 'phone' => '+27720000040', 'role' => 'super_admin'],
            ['name' => 'Ayanda Zwane', 'phone' => '+27720000042', 'role' => 'operations_admin'], ['name' => 'Bheki Mthethwa', 'phone' => '+27720000043', 'role' => 'support_agent'],
        ];
    }
}
