<?php

declare(strict_types=1);

namespace Modules\HubOps\Tests;

use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Tests\Structure;
use Tests\TestCase;

/**
 * Signs someone in (session + device, as after a full sign-in) for KasiHub Ops tests.
 */
final class Staff
{
    public static function as(TestCase $test, string $role, ?Scope $scope = null): User
    {
        return self::signIn($test, Structure::personWith($role, $scope));
    }

    public static function signIn(TestCase $test, User $user): User
    {
        app(ConsentService::class)->record($user, ['platform' => true]);
        $device = $user->devices()->create(['name' => 'Test browser']);
        $test->actingAs($user)->withSession(['device_id' => $device->id, 'last_activity_at' => now()->getTimestamp()]);

        return $user;
    }
}
