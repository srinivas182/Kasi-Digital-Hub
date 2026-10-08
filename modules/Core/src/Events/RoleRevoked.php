<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Identity\Models\User;

final class RoleRevoked extends PlatformEvent
{
    public const NAME = 'core.role.revoked';

    public const DESCRIPTION = 'A role was taken away from a person.';

    public function __construct(
        public readonly User $user,
        public readonly string $role,
        public readonly string $scopeType,
        public readonly ?string $scopeId,
        public readonly ?string $revokedBy = null,
    ) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): ?string
    {
        return $this->revokedBy;
    }

    public function payload(): array
    {
        return ['role' => $this->role, 'scope_type' => $this->scopeType, 'scope_id' => $this->scopeId];
    }
}
