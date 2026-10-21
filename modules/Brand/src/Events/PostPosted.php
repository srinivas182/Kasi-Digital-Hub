<?php

declare(strict_types=1);

namespace Modules\Brand\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class PostPosted extends PlatformEvent
{
    public const NAME = 'brand.post.posted';

    public const DESCRIPTION = 'A business marked a planned post as posted in its KasiBrand calendar.';

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
