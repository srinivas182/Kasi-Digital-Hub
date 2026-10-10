<?php

declare(strict_types=1);

namespace Modules\Partner\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class SupportConfirmed extends PlatformEvent
{
    public const NAME = 'partner.support.confirmed';

    public const DESCRIPTION = 'Support from a partner confirmed by both the partner and the entrepreneur.';

    /** @param array<string, string|int|bool|null> $details */
    public function __construct(public readonly User $user, public readonly ?string $by = null, public readonly array $details = []) {}

    public function userId(): string
    {
        return $this->user->id;
    }

    public function actorId(): ?string
    {
        return $this->by;
    }

    public function hubId(): ?string
    {
        return $this->user->home_hub_id;
    }

    public function payload(): array
    {
        return $this->details;
    }
}
