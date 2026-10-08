<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;

/**
 * Gives every demo account its role(s) and home hub (docs/demo.md).
 */
final class DemoRolesSeeder extends Seeder
{
    /** @var list<array{phone: string, hub: string|null, roles: list<array{0: string, 1: string}>, org?: string}> */
    public const ASSIGNMENTS = [
        ['phone' => '+27720000001', 'hub' => 'LP-GIY-TSU', 'roles' => [['job_seeker', 'self'], ['learner', 'self']]],
        ['phone' => '+27720000002', 'hub' => 'LP-GIY-CEN', 'roles' => [['learner', 'self'], ['job_seeker', 'self']]],
        ['phone' => '+27720000003', 'hub' => 'GP-JHB-SOW', 'roles' => [['entrepreneur', 'self']]],
        ['phone' => '+27720000004', 'hub' => 'LP-COL-MAL', 'roles' => [['learner', 'self']]],
        ['phone' => '+27720000010', 'hub' => null, 'org' => 'Mopani Fresh Market', 'roles' => [['employer_admin', 'organisation:Mopani Fresh Market']]],
        ['phone' => '+27720000011', 'hub' => 'LP-GIY-TSU', 'roles' => [['mentor', 'self']]],
        ['phone' => '+27720000012', 'hub' => null, 'org' => 'HBM EduTech', 'roles' => [['provider_admin', 'organisation:HBM EduTech']]],
        ['phone' => '+27720000020', 'hub' => 'LP-GIY-TSU', 'roles' => [['hub_facilitator', 'hub:LP-GIY-TSU']]],
        ['phone' => '+27720000021', 'hub' => 'LP-GIY-TSU', 'roles' => [['hub_manager', 'hub:LP-GIY-TSU']]],
        ['phone' => '+27720000022', 'hub' => null, 'roles' => [['city_coordinator', 'municipality:LIM331']]],
        ['phone' => '+27720000023', 'hub' => null, 'roles' => [['provincial_coordinator', 'province:LP']]],
        ['phone' => '+27720000024', 'hub' => 'LP-GIY-TSU', 'org' => 'Tsutsumani Youth Development Co-operative', 'roles' => [['hub_owner', 'hub:LP-GIY-TSU']]],
        ['phone' => '+27720000030', 'hub' => null, 'org' => 'Limpopo Youth Skills Programme (sample funder)', 'roles' => [['funder_manager', 'organisation:Limpopo Youth Skills Programme (sample funder)']]],
        ['phone' => '+27720000040', 'hub' => null, 'org' => 'Ku Tirhisana Consultancy (Pty) Ltd', 'roles' => [['super_admin', 'national']]],
        ['phone' => '+27720000041', 'hub' => null, 'org' => 'Ku Tirhisana Consultancy (Pty) Ltd', 'roles' => [['finance_admin', 'national']]],
        ['phone' => '+27720000042', 'hub' => null, 'org' => 'Ku Tirhisana Consultancy (Pty) Ltd', 'roles' => [['operations_admin', 'national']]],
        ['phone' => '+27720000043', 'hub' => null, 'org' => 'Ku Tirhisana Consultancy (Pty) Ltd', 'roles' => [['support_agent', 'national']]],
        ['phone' => '+27720000050', 'hub' => null, 'org' => 'Ubuntu Community Bank (demo)', 'roles' => [['partner_admin', 'organisation:Ubuntu Community Bank (demo)']]],
    ];

    public function run(RoleAssignments $assignments): void
    {
        foreach (self::ASSIGNMENTS as $row) {
            $user = User::query()->where('phone', $row['phone'])->firstOrFail();

            if ($row['hub'] !== null) {
                $hub = Hub::query()->with('municipality')->where('code', $row['hub'])->firstOrFail();
                $user->forceFill([
                    'home_hub_id' => $hub->id,
                    'municipality_id' => $hub->municipality_id,
                    'province_id' => $hub->municipality->province_id,
                    'place_name' => $hub->place?->name,
                ])->save();
            }

            if (isset($row['org'])) {
                Organisation::query()->where('name', $row['org'])->firstOrFail()->members()->syncWithoutDetaching([$user->id]);
            }

            foreach ($row['roles'] as [$role, $scope]) {
                $assignments->assign($user, $role, Scope::parse($scope));
            }
        }
    }
}
