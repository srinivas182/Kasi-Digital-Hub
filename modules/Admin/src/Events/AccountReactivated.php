<?php

declare(strict_types=1);

namespace Modules\Admin\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class AccountReactivated extends PlatformEvent
{
    public const NAME = 'admin.account.reactivated';

    public const DESCRIPTION = 'An administrator reactivated a suspended account.';

    public function __construct(public readonly User $user, public readonly string $by, public readonly string $reason) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): string
    {
        return $this->by;
    }

    public function hubId(): ?string
    {
        return $this->user->home_hub_id;
    }

    public function payload(): array
    {
        return ['reason' => $this->reason];
    }
}
