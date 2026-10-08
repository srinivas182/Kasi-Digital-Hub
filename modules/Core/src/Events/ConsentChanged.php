<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Identity\Models\User;

final class ConsentChanged extends PlatformEvent
{
    public const NAME = 'core.consent.changed';

    public const DESCRIPTION = 'A person gave or withdrew consent for a purpose (POPIA).';

    public function __construct(public readonly User $user, public readonly string $purpose, public readonly bool $granted, public readonly ?string $assistedBy = null) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): ?string
    {
        return $this->assistedBy;
    }

    public function payload(): array
    {
        return ['purpose' => $this->purpose, 'granted' => $this->granted];
    }
}
