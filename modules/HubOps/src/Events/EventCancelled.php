<?php

declare(strict_types=1);

namespace Modules\HubOps\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\HubOps\Models\HubEvent;

final class EventCancelled extends PlatformEvent
{
    public const NAME = 'hubops.event.cancelled';

    public const DESCRIPTION = 'A hub event was cancelled; everyone signed up was told why.';

    public function __construct(public readonly HubEvent $event, public readonly string $by) {}

    public function userId(): ?string
    {
        return null;
    }

    public function actorId(): string
    {
        return $this->by;
    }

    public function hubId(): string
    {
        return $this->event->hub_id;
    }

    public function payload(): array
    {
        return ['reason' => $this->event->cancel_reason];
    }
}
