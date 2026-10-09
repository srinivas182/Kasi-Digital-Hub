<?php

declare(strict_types=1);

namespace Modules\HubOps\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class AssistedRegistration extends PlatformEvent
{
    public const NAME = 'hubops.assisted.registration';

    public const DESCRIPTION = 'A facilitator registered a person at a hub (the person confirmed their phone and chose their own PIN).';

    public function __construct(public readonly User $user, public readonly string $by, public readonly string $hub) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): string
    {
        return $this->by;
    }

    public function hubId(): string
    {
        return $this->hub;
    }

    public function payload(): array
    {
        return [];
    }
}
