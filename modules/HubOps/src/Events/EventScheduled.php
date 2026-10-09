<?php

declare(strict_types=1);

namespace Modules\HubOps\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\HubOps\Models\HubEvent;

final class EventScheduled extends PlatformEvent
{
    public const NAME = 'hubops.event.scheduled';

    public const DESCRIPTION = 'A hub scheduled an event (job day, workshop, info session or class).';

    public function __construct(public readonly HubEvent $event) {}

    public function userId(): ?string
    {
        return null;
    }

    public function actorId(): ?string
    {
        return $this->event->created_by;
    }

    public function hubId(): string
    {
        return $this->event->hub_id;
    }

    public function payload(): array
    {
        return ['type' => $this->event->type, 'capacity' => $this->event->capacity, 'starts_at' => $this->event->starts_at->toIso8601String()];
    }
}
