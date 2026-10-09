<?php

declare(strict_types=1);

namespace Modules\Work\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class RetentionChecked extends PlatformEvent
{
    public const NAME = 'work.retention.checked';

    public const DESCRIPTION = 'A young person answered the 30- or 90-day "still working there?" check-in.';

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
