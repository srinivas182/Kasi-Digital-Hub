<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;

/**
 * Shared helpers for admin controllers.
 */
trait Concerns
{
    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function can(Request $request, string $permission): bool
    {
        return app(AccessResolver::class)->hasPermission($this->actor($request), $permission);
    }

    /**
     * Reasons are mandatory for sensitive actions; they go to the audit log.
     *
     * @return array{reason: string}
     */
    protected function validateReason(Request $request): array
    {
        /** @var array{reason: string} */
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
    }
}
