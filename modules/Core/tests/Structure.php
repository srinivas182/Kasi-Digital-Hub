<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Database\Seeders\DemoHierarchySeeder;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;
use Tests\TestCase;

/**
 * Builds the demo national structure (geography, 12 hubs on different packages,
 * organisations) for access tests.
 */
final class Structure
{
    public static function seed(TestCase $test): void
    {
        $test->seed([ConsentDocumentSeeder::class, DemoHierarchySeeder::class]);
    }

    public static function hub(string $code): Hub
    {
        return Hub::query()->where('code', $code)->firstOrFail();
    }

    public static function org(string $name): Organisation
    {
        return Organisation::query()->where('name', $name)->firstOrFail();
    }

    public static function city(string $code): Municipality
    {
        return Municipality::query()->where('code', $code)->firstOrFail();
    }

    public static function province(string $code): Province
    {
        return Province::query()->where('code', $code)->firstOrFail();
    }

    /** A person with one role, in the natural scope for that role. */
    public static function personWith(string $role, ?Scope $scope = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(RoleAssignments::class)->assign($user, $role, $scope ?? self::defaultScope($role));

        return $user->refresh();
    }

    public static function defaultScope(string $role): Scope
    {
        return match ($role) {
            'job_seeker', 'learner', 'entrepreneur', 'mentor' => Scope::self(),
            'employer_admin', 'recruiter' => Scope::organisation(self::org('Mopani Fresh Market')),
            'provider_admin', 'course_author', 'assessor_moderator' => Scope::organisation(self::org('HBM EduTech')),
            'partner_admin', 'partner_agent' => Scope::organisation(self::org('Ubuntu Community Bank (demo)')),
            'funder_manager', 'funder_viewer' => Scope::organisation(self::org('Limpopo Youth Skills Programme (sample funder)')),
            'hub_owner', 'hub_manager', 'hub_facilitator' => Scope::hub(self::hub('LP-GIY-TSU')),
            'city_coordinator' => Scope::municipality(self::city('LIM331')),
            'provincial_coordinator' => Scope::province(self::province('LP')),
            default => Scope::national(),
        };
    }
}
