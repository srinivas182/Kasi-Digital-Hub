<?php

declare(strict_types=1);

namespace Modules\Admin\Tests;

use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Tests\Structure;
use Tests\TestCase;

/**
 * Signs a staff member into the console (session + device, as after a full sign-in).
 */
final class Console
{
    public static function as(TestCase $test, string $role): User
    {
        $user = Structure::personWith($role);
        app(ConsentService::class)->record($user, ['platform' => true]);
        $device = $user->devices()->create(['name' => 'Test browser']);
        $test->actingAs($user)->withSession(['device_id' => $device->id, 'last_activity_at' => now()->getTimestamp()]);

        return $user;
    }
}
