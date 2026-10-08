<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Identity\Models\User;

final class UserRegistered extends PlatformEvent
{
    public const NAME = 'core.user.registered';

    public const DESCRIPTION = 'A person created an account (self-service or assisted at a hub).';

    public function __construct(public readonly User $user, public readonly string $channel = 'self') {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function hubId(): ?string
    {
        return $this->user->home_hub_id;
    }

    public function payload(): array
    {
        return ['age_band' => $this->user->age_band, 'channel' => $this->channel];
    }
}
