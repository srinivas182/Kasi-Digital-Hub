<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Core\Events\RoleAssigned;
use Modules\Core\Events\RoleRevoked;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Grants and revokes roles. Staff, organisation and national roles switch on the
 * authenticator second step automatically. Every change is audited.
 */
final readonly class RoleAssignments
{
    public function __construct(
        private RoleRegistry $roles,
        private AccessResolver $access,
        private AuditLogger $audit,
    ) {}

    public function assign(User $user, string $roleKey, Scope $scope, ?User $by = null, ?\DateTimeInterface $expiresAt = null): RoleAssignment
    {
        $role = $this->roles->get($roleKey);

        if ($role->scope !== $scope->type) {
            throw new InvalidArgumentException("Role [{$roleKey}] must be given at {$role->scope} level, not {$scope->type}.");
        }

        if ($user->isMinor() && ($role->staff || ! in_array($role->module, (array) config('kasi.age.minor_modules'), true))) {
            throw new InvalidArgumentException("Role [{$roleKey}] is not available to people under ".config('kasi.age.full_access_age').'.');
        }

        $assignment = RoleAssignment::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => $roleKey, 'scope_type' => $scope->type, 'scope_id' => $scope->id],
            ['granted_by' => $by?->id, 'expires_at' => $expiresAt],
        );

        $this->refresh($user);
        $this->audit->record('role.assigned', $user, meta: ['role' => $roleKey, 'scope' => $scope->type, 'scope_id' => $scope->id], actor: $by);
        event(new RoleAssigned($user, $roleKey, $scope->type, $scope->id, $by?->id));

        return $assignment;
    }

    public function revoke(User $user, string $roleKey, Scope $scope, ?User $by = null): bool
    {
        $deleted = RoleAssignment::query()
            ->where('user_id', $user->id)->where('role', $roleKey)
            ->where('scope_type', $scope->type)->where('scope_id', $scope->id)
            ->delete() > 0;

        if ($deleted) {
            $this->refresh($user);
            $this->audit->record('role.revoked', $user, meta: ['role' => $roleKey, 'scope' => $scope->type, 'scope_id' => $scope->id], actor: $by);
            event(new RoleRevoked($user, $roleKey, $scope->type, $scope->id, $by?->id));
        }

        return $deleted;
    }

    /** @return Collection<int, RoleAssignment> */
    public function for(User $user): Collection
    {
        return RoleAssignment::query()->where('user_id', $user->id)->active()->orderBy('role')->get();
    }

    /** Bump the access version (cache key) and keep the staff second-step requirement in step with roles. */
    private function refresh(User $user): void
    {
        $staff = RoleAssignment::query()->where('user_id', $user->id)->active()->pluck('role')
            ->contains(fn (string $key): bool => $this->roles->find($key)?->staff === true);

        $user->forceFill(['access_version' => $user->access_version + 1, 'two_factor_required' => $staff])->save();
        $this->access->forget($user);
    }
}
