<?php

declare(strict_types=1);

namespace Modules\Market\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

/** Base for KasiMarket platform events: the trader the event is about, who caused it, and small details. */
abstract class MarketEvent extends PlatformEvent
{
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
