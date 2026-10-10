<?php

declare(strict_types=1);

namespace Modules\Learn\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Identity\Models\User;

final class Enrolled extends PlatformEvent
{
    public const NAME = 'learn.enrolled';

    public const DESCRIPTION = 'A learner enrolled in a course.';

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
