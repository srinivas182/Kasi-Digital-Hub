<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Identity\Models\User;

final class RoleAssigned extends PlatformEvent
{
    public const NAME = 'core.role.assigned';

    public const DESCRIPTION = 'A person was given a role in a scope (own account, organisation, hub, city, province, national).';

    public function __construct(
        public readonly User $user,
        public readonly string $role,
        public readonly string $scopeType,
        public readonly ?string $scopeId,
        public readonly ?string $grantedBy = null,
    ) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): ?string
    {
        return $this->grantedBy;
    }

    public function hubId(): ?string
    {
        return $this->scopeType === 'hub' ? $this->scopeId : null;
    }

    public function payload(): array
    {
        return ['role' => $this->role, 'scope_type' => $this->scopeType, 'scope_id' => $this->scopeId];
    }
}
